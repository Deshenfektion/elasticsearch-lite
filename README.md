# elasticsearch-lite

A small full-text search engine, written to understand how Elasticsearch and Lucene work
underneath the query DSL.

Not a clone. The point is to build the parts that actually do the work — a document
pipeline, an inverted index, a query parser, a ranking model, a highlighter — in PHP, with
no search library to lean on.

```
documents → parser → analyzer → inverted index (MySQL) → ranking → query engine → results
```

## What works so far

- **Ingestion** of plain text, HTML and JSON documents through media-type specific parsers,
  keeping both the original text and the searchable structures.
- **Analysis** through a configurable chain: Unicode tokenizer, apostrophe stripping,
  lowercasing, ASCII folding, length filtering, English stop words, Porter stemming.
- **An inverted index** with per-field term frequencies, document frequencies, field
  lengths and delta-encoded positions, updated transactionally.

Query parsing, ranking and highlighting are next.

## The index

`terms` holds one row per unique term with its document and total frequency. `postings`
holds one row per `(term, field, document)` with the term frequency, the field length and a
blob of positions. The primary key order `(term_id, field_id, document_id)` is the whole
trick: one term's postings are contiguous and already sorted by document id, so reading a
posting list is an index range scan and intersecting two of them is a merge rather than a
nested loop.

Positions are gap-encoded variable-byte integers in a blob instead of a row per position.
A row per position would have multiplied the table size for data that is only ever read as
a whole list.

Field length is denormalised into the posting row because scoring needs it at exactly the
moment it reads the posting, and collection statistics live in `index_state` as counters so
that IDF does not cost a `COUNT(*)` per query.

## Analysis trade-offs

Every filter in the chain trades recall against precision, and each one is configurable:

| Filter          | Effect                              | Cost                                  |
| --------------- | ----------------------------------- | ------------------------------------- |
| ASCII folding   | `café` matches `cafe`               | German `schön`/`schon` collapse       |
| Stop words      | ~30% smaller index                  | `"to be or not to be"` is unsearchable |
| Porter stemming | `indexing` matches `indexes`        | `university`/`universe` collapse      |

The same chain runs over documents and over queries, which is the only reason the two ever
match.

## Stack

PHP 8.4 with no runtime dependencies, MySQL 8.4 (SQLite for tests), vanilla JavaScript and
SCSS. Composer and PHPUnit for the backend, ESLint and Prettier for the frontend, Docker
Compose to run the whole thing.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests run against in-memory SQLite, so the suite needs no services.

## Status

The pipeline runs end to end from a document to postings on disk. Next: the boolean query
parser, TF-IDF scoring and the search API.
