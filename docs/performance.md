# Performance

Measured numbers live in [bench/RESULTS.md](../bench/RESULTS.md). This document is about
where the time goes and which decisions were made because of it.

## Complexity

| Operation              | Cost                                                                 |
| ---------------------- | -------------------------------------------------------------------- |
| Analyse a document     | O(tokens); one regex pass plus a constant number of filter passes    |
| Index a document       | O(unique terms) rows written, in a constant number of statements     |
| Update a document      | O(old postings + new postings)                                       |
| Single-term query      | O(df) postings scored                                                |
| Conjunction of k terms | O(min df · k · log max df) with galloping skips                      |
| Disjunction of k terms | O(Σ df · log k) through the scorer heap                              |
| Phrase of k terms      | O(candidates · Σ positions) after the conjunction narrows candidates |
| Prefix or wildcard     | O(expansions) dictionary rows + a disjunction over them              |
| Top-k collection       | O(matches · log k) in a bounded min-heap                             |
| Highlighting one hit   | O(field bytes) — the stored field is re-analysed                     |

The thing that dominates is the number of postings scored, which is why a term matching
4,996 of 5,000 documents costs 21 ms while a term matching 46 costs 4 ms in the same
index.

## Where a 21 ms query goes

For the common-term query on the 5,000-document MySQL corpus, roughly:

- **~2 ms** parse, analyse and plan the query, including the dictionary lookup.
- **~9 ms** fetch the posting rows: one `SELECT` returning ~5,000 rows, hydrated into
  objects.
- **~6 ms** iterate and score every match, then keep the top ten in the heap.
- **~2 ms** hydrate the ten result documents plus their tags.
- **~2 ms** highlight two fields per hit and count three facet groups.

That shape is what makes the optimisations below worth doing and the ones after them
not.

## What was optimised, and what it bought

**Batch the posting fetch.** Planning collects every term the query needs before
execution, so one `WHERE term_id IN (...)` replaces one query per clause. A three-term
OR is one round trip instead of three.

**Skip the positions blob.** `PlannedQuery::$needsPositions` is false unless a phrase
survived rewriting, and the reader omits `positions` from the `SELECT`. On the benchmark
corpus that is about 1 MB of blob not read for the common case.

**Galloping search in `advance()`.** Conjunctions sort clauses by posting count and let
the cheapest lead. Intersecting a rare term with a common one costs
`O(rare · log common)`, not `O(common)`. This is the difference between 11.7 ms
(`+common +medium`) and the 21 ms the common term costs on its own.

**Denormalise `field_length` into the posting row.** BM25 needs it during scoring.
Keeping it in the posting means the scoring loop issues no queries at all.

**Counters instead of aggregates.** `index_state` holds the document count and per-field
length totals, so IDF and average field length are one small `SELECT`, not `COUNT(*)`
and `AVG()` over the whole collection on every query.

**Prepared-statement reuse.** `Connection` caches `PDOStatement` objects by SQL text.
Indexing runs the same handful of statements thousands of times, and reuse removes the
parse and plan cost from each one.

**Precompute per-field constants in the scorer.** `TermScorer` computes IDF, the average
field length and the field boost once in its constructor instead of per posting. On the
5,000-document corpus this moved the common-term p50 from 21.99 ms to 20.92 ms and the
three-term OR from 31.73 to 29.90 ms — about 5%, measured before and after.

**Skip match collection when nothing needs it.** Facet counting needs the full matched
id list; a query with `facets=0` does not. Passing that flag down avoids building an
array of up to 20,000 integers, which is most of the difference between `filtered` at
8.4 ms and `filtered, facets off` at 3.4 ms (the rest is the three facet queries).

## Caching

Three layers, with very different value:

| Layer         | Hit rate in practice  | Why                                                    |
| ------------- | --------------------- | ------------------------------------------------------ |
| Term metadata | High                  | Small, hot, changes only on write; backed by APCu      |
| Posting lists | Moderate              | Large; only worth keeping for genuinely frequent terms |
| Whole results | High for head traffic | Query distributions are skewed; needs a short TTL      |

PHP-FPM gives a request no memory of the last one, so an in-process LRU only pays off
_within_ a request (repeated terms across clauses, suggest-then-search) and in
long-running CLI or benchmark processes. That is why the term and result caches use APCu
when the extension is available and fall back to the in-process LRU otherwise, and why
the benchmark reports a `cold p50` column with the caches flushed before every call: it
is the honest number for a fresh worker.

Result caching is off in the test suite and in the benchmark, because a benchmark that
measures a warm result cache measures the cache.

## Indexing throughput

220 documents/s on MySQL, 518 on SQLite, at ~185 tokens per document. Each document is
one transaction containing a document upsert, a posting delete (on update), a bulk
posting insert, a bulk term-frequency update and a state update. The bulk statements are
chunked to stay under the driver's placeholder limit (8,000 for MySQL, 900 for SQLite).

Faster indexing is available and deliberately not taken: batching many documents into
one transaction would multiply throughput, but it also means a failure loses the whole
batch and the term frequency deltas of a partially applied batch are hard to reason
about. Per-document transactions keep the index consistent after any crash, which for a
search engine that can be rebuilt from its own document store is the right trade.

## Costs that surprised me

**Facet counting.** Three grouped queries over the matched ids more than doubled the
cost of an otherwise cheap filtered query. Capping at 5,000 ids and reporting
`truncated` keeps it bounded.

**Highlighting re-analyses the field.** Finding matches means running the analyzer over
the stored text again to recover offsets. It is O(field length) per hit, per field, and
it is the reason fragments are built from at most `max_fragments` windows rather than
the whole document. Storing offsets in the index would remove the re-analysis at the
cost of a much larger index.

**Deep paging is not free.** `page=20` still scores every match, because the top-k heap
has to see all of them to know which twenty pages exist. `deep page` costs 21.8 ms
against 20.9 ms for page 1 — the extra is heap churn, not I/O. Search-after with a sort
key would fix it properly.

## Known limitations

- **No block-max or WAND pruning.** Every match is scored so that `total` is exact. This
  is the single biggest structural gap versus Lucene and the reason common-term queries
  cost what they do.
- **No posting compression beyond positions.** Document ids are one row each rather than
  a delta-compressed block per term.
- **No skip lists on disk.** Galloping happens over an in-memory array after the rows
  are read; the read itself is a full index range scan for the term.
- **Single node, single process.** No sharding, no replication, no background merging.
- **The filter set is materialised.** A filter matching more than 20,000 documents is
  truncated; a bitset per field value would scale further.
