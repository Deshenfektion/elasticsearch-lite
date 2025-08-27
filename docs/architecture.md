# Architecture

The engine is a pipeline. Documents enter at one end, get taken apart, and end up as
rows in an inverted index; queries enter at the other end, get taken apart the same way,
and are answered by walking those rows. Every stage is a separate namespace with one
job.

```
POST /documents
      │
      ▼
  Parser          text/plain, text/html, application/json  ─→  ParsedDocument
      │
      ▼
  Analysis        tokenize → apostrophe → lowercase → fold → length → stop → stem
      │
      ▼
  IndexWriter     document row + per-field postings + term statistics   (one transaction)
      │
      ▼
   MySQL          documents, terms, postings, document_fields, index_state
      ▲
      │
  IndexReader     term dictionary lookups, posting lists, collection statistics
      │
      ▼
  QueryPlanner    AST → analysed terms → dictionary expansion → PlannedQuery
      │
      ▼
  ScorerFactory   term / phrase / conjunction / disjunction / exclusion / filtered scorers
      │
      ▼
  Searcher        iterate, score, collect top k
      │
      ▼
  Highlighter     locate matches → score passages → render fragments
      │
      ▼
GET /search
```

## Layers

| Namespace           | Responsibility                                                             |
| ------------------- | -------------------------------------------------------------------------- |
| `EsLite\Document`   | Value objects: `SourceDocument`, `ParsedDocument`, `StoredDocument`        |
| `EsLite\Parser`     | Media-type specific parsers plus a registry that resolves them             |
| `EsLite\Analysis`   | Tokenizer, token filters, stemmers, the `Analyzer` that chains them        |
| `EsLite\Index`      | Postings, term dictionary, iterators, varint codec, writer and reader      |
| `EsLite\Query`      | Lexer, recursive-descent parser, AST nodes                                 |
| `EsLite\Search`     | Planning, scorers, collectors, filters, request and response objects       |
| `EsLite\Ranking`    | Scoring models (TF-IDF, BM25), ranking configuration, explanations         |
| `EsLite\Highlight`  | Match location, passage selection, fragment rendering                      |
| `EsLite\Repository` | Every SQL statement in the project lives here                              |
| `EsLite\Service`    | Use cases: indexing, searching, suggesting, reindexing, statistics, health |
| `EsLite\Http`       | Router, request, response, controllers, exception mapping                  |
| `EsLite\Console`    | The `bin/console` commands                                                 |
| `EsLite\Support`    | Config, clock, stopwatch, caches, database connection and migrations       |

Dependencies point inwards. `Http` and `Console` depend on `Service`; `Service` depends
on `Search`, `Index` and `Repository`; `Index` depends on `Analysis` and `Repository`;
`Analysis`, `Query` and `Ranking` depend on nothing but `Support`. Nothing below
`Repository` knows that MySQL exists, and nothing above it writes SQL.

## Composition

`EsLite\Application` is the composition root: one class, one memoised factory method per
service, wired by hand. There is no container, no autowiring and no reflection, which
means the object graph is greppable and a constructor signature change is a compile-time
problem rather than a runtime one.

```php
$app = Application::boot(__DIR__);
$app->indexingService()->ingest(SourceDocument::fromArray($payload));
$app->searchService()->search(SearchRequest::fromArray($_GET, $app->config()));
```

Tests construct the same graph with an in-memory SQLite connection, which is the only
reason the suite runs in half a second.

## Two-phase query execution

Query execution is split so that the expensive part happens once:

1. **Plan** (`QueryPlanner`). Walk the AST. Run each term through the same analyzer the
   documents saw. Expand `prefix*` and `wild?ard` against the term dictionary, capped at
   `search.max_expansions`. The result is a `PlannedQuery`: a rewritten AST, the flat
   list of terms it needs, and whether any clause needs positions.
2. **Execute** (`Searcher` + `ScorerFactory`). Fetch every posting list the plan needs
   in a single round trip, build a scorer tree over them, then iterate.

The split matters for two reasons. Fetching postings in one batch turns N dictionary
lookups into one `IN (...)`, and knowing up front whether positions are needed lets the
reader leave the `positions` blob out of the `SELECT` for queries that will never look
at it.

## Scorers

Scorers are iterators over document ids that can also produce a score, which is Lucene's
shape. Each one is small enough to test on its own.

| Scorer                   | Role                                                           |
| ------------------------ | -------------------------------------------------------------- |
| `TermScorer`             | One term, one or more fields, weighted by field boost          |
| `PhraseScorer`           | Conjunction of terms plus a positional check per field         |
| `ConjunctionScorer`      | Leapfrog over required clauses, cheapest clause leads          |
| `DisjunctionScorer`      | Min-heap over optional clauses, coordination factor            |
| `RequiredOptionalScorer` | Required clauses drive iteration, optional clauses add score   |
| `ExclusionScorer`        | Skips documents matched by a prohibited clause                 |
| `FilteredScorer`         | Skips documents outside the structured filter set              |
| `ConstantScoreScorer`    | Match-all and expansion clauses that should not affect ranking |
| `EmptyScorer`            | A clause whose term is not in the dictionary                   |

`ScorerFactory` maps AST nodes to this tree: required clauses become a conjunction,
optional clauses a disjunction, prohibited clauses an exclusion wrapper, and a filter
set wraps whatever came out.

## Storage decisions

- **Postings are rows, not files.** The primary key is
  `(term_id, field_id, document_id)`, so reading one term's postings is an ordered index
  range scan and intersecting two of them is a merge. A purpose-built codec would
  compress better; a relational table makes the trade-offs visible and the index
  inspectable with SQL.
- **Positions are a blob per posting.** Delta-gapped varints in a `BLOB` column instead
  of one row per position. See [inverted-index.md](inverted-index.md).
- **Field length is denormalised into the posting row.** BM25 needs it while scoring,
  and copying two bytes into the posting removes a second lookup from the hot path.
- **Collection statistics are counters, not aggregates.** `index_state` holds the
  document count and per-field length totals, maintained transactionally by the writer,
  so IDF and the average field length are an O(1) read instead of a table scan.
- **No foreign key on `postings`.** It is the hottest and largest table; the writer
  maintains it explicitly, and skipping the constraint check keeps inserts cheap.

## Request lifecycle

`public/index.php` boots the application, builds a `Request` from PHP globals and hands
it to `Kernel::handle()`. The kernel dispatches through `Router`, and any `Throwable`
goes to `ExceptionMapper`, which is the only place that decides what an exception looks
like over HTTP. Controllers do three things: read input, call one service, return a
`Response`.

nginx serves `public/` directly and forwards `/api/*` to PHP-FPM, so static assets never
touch PHP.
