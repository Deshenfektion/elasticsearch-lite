# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 — 2025-08-29

First complete version: a working search engine with an index, a query language,
ranking, highlighting, an HTTP API and a UI.

### Ingestion

- Document pipeline for `text/plain`, `text/markdown`, `text/html` and
  `application/json`, with a parser registry resolving by media type.
- HTML parsing through PHP 8.4's `Dom\HTMLDocument`: script and style stripping,
  block-aware text extraction, title, author and keyword metadata.
- JSON parsing with a configurable field mapping and a fallback that flattens unknown
  shapes.
- Content-checksum short-circuit so re-indexing an unchanged document is free.
- Batch indexing (up to 500 documents per request) and a `POST /reindex` rebuild.

### Analysis

- `StandardTokenizer`: Unicode-aware, keeps decimals and intra-word apostrophes, records
  positions and byte offsets.
- Filters: apostrophe, lowercase, ASCII folding, length, English stop words, Porter
  stemming.
- `PorterStemmer` implemented from the 1980 algorithm, wrapped in an LRU cache.
- Stop-word removal leaves position holes so phrase queries keep working.
- Query-time normalisation path that skips stemming for prefix and wildcard terms.

### Index

- `terms` and `postings` tables keyed `(term_id, field_id, document_id)` for ordered
  per-term scans; per-field term frequency, field length and delta-encoded positions.
- Variable-byte codec for positions, measured at 1.13 bytes per position.
- Transactional create, update and delete with document- and total-frequency deltas
  applied in one batched statement.
- Collection statistics kept as counters in `index_state` so IDF and average field
  length are an O(1) read.
- Galloping search in `PostingIterator::advance()`, replacing the original linear scan.
- Term and posting-list caches, backed by APCu when available.

### Query and ranking

- Lexer and recursive-descent parser for required, prohibited, boolean, phrase,
  field-restricted, prefix, wildcard and grouped queries, with positional parse errors.
- `QueryPlanner` rewriting stage: analysis, dictionary expansion (capped), phrase
  offsets, and a flag that decides whether positions are read at all.
- Scorer tree: term, phrase, conjunction (leapfrog), disjunction (min-heap with
  coordination), required-optional, exclusion, filtered and constant-score scorers.
- BM25 and TF-IDF models behind one interface, selectable at runtime, with per-field
  boosts and a phrase boost.
- Bounded min-heap top-k collection and per-hit score explanations.

### Search API and UI

- `POST /documents`, `GET|DELETE /documents/{id}`, `POST /reindex`, `GET /search`,
  `GET /suggest`, `GET /statistics`, `GET /health`.
- Structured filters (category, author, tags, date range) composed with the full-text
  query, plus facet counts over the matched set.
- Highlighting with passage scoring, multiple fragments, phrase spans and HTML escaping.
- Suggestions from the term dictionary and from the search log.
- Instant-search UI: debounced queries, cancelled in-flight requests, keyboard-navigable
  suggestions, filter sidebar, sorting, pagination, ranking details and URL state.

### Infrastructure

- Docker Compose stack: nginx, PHP-FPM with opcache and APCu, MySQL 8.4.
- Dual-driver storage layer (MySQL and SQLite) behind a dialect abstraction, with a
  migration runner.
- Benchmark harness with a seeded corpus generator, percentile reporting and JSON
  output.
- 263 tests across unit and feature suites, running against in-memory SQLite.
