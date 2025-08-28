# The inverted index

The index answers one question quickly: _which documents contain this term, how often,
and where_. Everything in this document exists to make that lookup cheap and to keep it
correct when documents change.

## Schema

```sql
terms (
  id                  BIGINT UNSIGNED PK,
  term                VARBINARY(128) UNIQUE,
  document_frequency  INT,          -- documents containing the term
  total_frequency     BIGINT        -- occurrences across the collection
)

postings (
  term_id         BIGINT UNSIGNED,
  field_id        TINYINT UNSIGNED,
  document_id     BIGINT UNSIGNED,
  term_frequency  SMALLINT UNSIGNED,
  field_length    SMALLINT UNSIGNED,
  positions       BLOB,
  PRIMARY KEY (term_id, field_id, document_id),
  KEY idx_postings_document (document_id)
)

document_fields (document_id, field_id, length)   -- per-field lengths, for reindexing
index_state     (stat_key, stat_value)            -- collection counters
```

Three decisions carry most of the weight.

**The primary key order is the access pattern.** `(term_id, field_id, document_id)`
means the rows for one term are physically adjacent and already sorted by document id.
Reading a posting list is a range scan of a clustered index, and intersecting two
posting lists is a merge over two sorted streams instead of a nested loop.

**`term` is `VARBINARY`, not `VARCHAR`.** Terms are compared byte-for-byte. A
case-insensitive or accent-insensitive collation would make `Café` and `cafe` the same
key at the database level, which would silently paper over the analyzer and make the
term dictionary a different thing than the analysis chain produced. Analysis decides
equality; storage just stores bytes.

**`field_length` is denormalised into the posting.** BM25 needs the length of the field
the term was found in, at the moment it scores that posting. Two bytes per posting buys
a scoring loop that never issues a second query. `document_fields` keeps the same data
in normal form for reindexing and statistics.

## Positions

Positions are delta-gapped and variable-byte encoded into the `positions` blob:

```
positions      3     9    10    47
gaps           3     6     1    37
varint       0x03  0x06  0x01  0x25          → 4 bytes
```

`VarIntCodec::encodeSorted()` sorts, differences, then writes seven bits per byte with
the high bit as a continuation flag. Gaps inside one field are usually small, so most
positions cost a single byte. The measured cost on the benchmark corpus is **1.13 bytes
per position** across 924,431 tokens.

The alternative — one row per position — would have turned 512,313 posting rows into
924,431 position rows, each with its own primary key entry, for data that is only ever
read as a whole list. Decoding is lazy: `Posting::positions()` decodes on first access,
and the reader does not even select the blob for queries that contain no phrase clause.

## Reading

```php
$lists = $reader->postingLists(['invert', 'index'], withPositions: false);
$iterator = $lists['index']->iterator();

while (($doc = $iterator->next()) !== DocIdIterator::NO_MORE_DOCS) {
    // ...
}
```

`IndexReader::postingLists()` resolves every term in one dictionary lookup, fetches
every posting in one `WHERE term_id IN (...)` ordered by `term_id, document_id`, and
groups the rows into `PostingList` objects keyed by document. Both stages are cached per
term.

`PostingIterator` is the primitive the scorers stand on:

| Method             | Contract                                                   |
| ------------------ | ---------------------------------------------------------- |
| `next()`           | Advance one document, or `NO_MORE_DOCS`                    |
| `advance($target)` | First document `>= $target`, or `NO_MORE_DOCS`; idempotent |
| `cost()`           | Number of postings, used to order conjunction clauses      |

`advance()` uses galloping (exponential) search: double the step until the target is
passed, then binary search the bracket. Intersecting a 12-posting list with a
400,000-posting list therefore costs about 12 log n steps instead of 400,000.
`ConjunctionScorer` sorts its clauses by `cost()` and lets the cheapest one lead, so the
rare term drives the loop and the common term skips.

## Writing

`IndexWriter::write()` runs in one transaction:

1. Load the existing document by external id. If the checksum matches, stop and report
   `unchanged` — re-posting an identical document is free.
2. Analyse every field into `DocumentAnalysis`: per field, the length and, per term, the
   frequency and position list.
3. Upsert the document row; resolve the category and tags.
4. If the document existed, read its old postings (joined to `terms` for the term text),
   delete them, and record a negative delta of `(-1 document, -frequency)` per term.
5. Ensure a `terms` row exists for every new term, then bulk-insert the postings and the
   per-field lengths.
6. Merge the negative and positive deltas per term and apply them in **one** batched
   `INSERT ... ON DUPLICATE KEY UPDATE document_frequency = document_frequency + ...`.
7. Apply the same kind of delta to `index_state`.

Step 6 is the part that is easy to get wrong. `document_frequency` is the number of
documents containing a term, so it moves by at most one per document regardless of how
often the term occurs; `total_frequency` moves by the occurrence count. A stale document
frequency does not throw an error — it quietly distorts the IDF of every query that
touches the term. The test suite asserts both counters after create, update and delete
for exactly this reason.

## Deleting

Deletion is the same bookkeeping in reverse: read the postings for the document, delete
them, subtract the deltas, drop the document row and decrement the document count. Terms
whose document frequency reaches zero are left in place with `document_frequency = 0` —
cheaper than deleting the row, and every dictionary query already filters on
`document_frequency > 0`. `TermRepository::pruneEmpty()` cleans them up when it is
convenient.

There is no deletion marker and no segment merging. Updates are in-place, which is the
opposite of Lucene's design and the right trade at this scale: writes are rare, and an
in-place update means a search never has to check whether a document it just found is
actually dead.

## Collection statistics

IDF needs the collection size; BM25 needs the average field length. Computing either
with `COUNT(*)` or `AVG(length)` per query would dominate the query cost, so
`index_state` holds them as counters:

```
document_count      5000
field_1_documents   5000     field_1_length     45231
field_2_documents   4993     field_2_length     14887
field_3_documents   5000     field_3_length    864313
```

`CollectionStatistics` reads that table once per request and derives the average by
division. `clearIndex()` resets the counters and immediately restores `document_count`
from the documents table, because the documents survive an index rebuild — getting that
wrong makes every IDF zero after a reindex, which is a bug this project actually shipped
and then fixed.

## Caches

| Cache        | Key               | Backing               | Invalidated by                      |
| ------------ | ----------------- | --------------------- | ----------------------------------- |
| Term         | term text         | APCu, else in-process | `IndexCache::invalidate()` on write |
| Posting list | `term:positions`  | in-process LRU        | same                                |
| Result       | request signature | APCu, else in-process | 30 s TTL                            |

The term cache stores misses as well as hits, so a repeated search for a term that does
not exist costs nothing after the first lookup. Posting lists are only cached
in-process: they are large, and serialising them into APCu costs more than the query
they save. Every write path calls `IndexCache::invalidate()` with the terms it touched.
