# elasticsearch-lite

A full-text search engine from scratch: tokenizer, inverted index, query parser, BM25
ranking, highlighter, facets, suggestions, a REST API. No search library, no ORM, zero
runtime dependencies. Built to understand how Elasticsearch/Lucene actually work.

```
documents → parser → analyzer → inverted index (MySQL) → query planner → scorers → highlighter
```

## What's in it

- Parsers for text, HTML, JSON
- Analyzer chain: tokenize, strip apostrophes, lowercase, ASCII fold, stop words, Porter stemming
- Inverted index with term/doc frequency, field length, delta-encoded positions — transactional on write
- Query language: `+required -prohibited "phrase" field:term prefix* wild?ard (a OR b)`
- BM25 and TF-IDF ranking, field boosts, phrase boost, per-hit score explanations
- Structural filters + facet counts
- Highlighting, autocomplete, query suggestions
- REST API + vanilla-JS instant-search UI

263 tests, ~0.5s, in-memory SQLite.

## Stack

PHP 8.4 (no runtime deps) · MySQL 8.4 / SQLite for tests · vanilla JS, no framework ·
Docker Compose (nginx, PHP-FPM, MySQL)

## Run it

```bash
cp .env.example .env
make up
open http://localhost:8080
```

Boots with a 20-document demo corpus already indexed.

```bash
make search Q='"posting list"'   # search from the CLI
make stats                       # index/cache stats
make reindex
make test                        # 263 tests
make down
```

## The interesting bits

**Index layout:** `postings` primary key is `(term_id, field_id, document_id)` — one
term's postings are contiguous and sorted, so a term lookup is a range scan and an AND
is a merge. Positions are gap-encoded varints: 1.13 bytes/position measured, vs. one row
per position. → [docs/inverted-index.md](docs/inverted-index.md)

**Ranking:** BM25 (`k1=1.2, b=0.75`) by default, field-weighted (title 3x, tags 2x, body
1x), coordination factor, 2x phrase boost. TF-IDF via `RANKING_MODEL=tfidf`. Every hit
can explain its own score (`?explain=1`). → [docs/ranking.md](docs/ranking.md)

**Query language:** full grammar, rewriting, expansion limits →
[docs/query-language.md](docs/query-language.md)

**Perf:** M1 Pro, MySQL, 5k docs — term queries 4-30ms p50, phrase 5.5ms, filtered
8.4ms. No block-max/WAND yet, so a term hitting most of the collection is the worst
case. → [bench/RESULTS.md](bench/RESULTS.md), [docs/performance.md](docs/performance.md)

```bash
make bench
```

## Layout

```
src/Analysis/    tokenizer, filters, stemmers
src/Document/    document lifecycle
src/Parser/      text/HTML/JSON parsers
src/Index/       postings, term dictionary, varint codec
src/Query/       lexer, parser, AST
src/Search/      planner, scorers, collectors
src/Ranking/     BM25, TF-IDF, explanations
src/Highlight/   passage scoring, fragment rendering
src/Repository/  SQL
src/Service/     indexing, search, suggest, stats
src/Http/        router, controllers
src/Console/     bin/console commands
web/             frontend, built by web/build.mjs
bench/           benchmark harness + results
docs/            architecture, analysis, index, query, ranking, API, perf
```

## Not done yet

Block-max WAND, compressed posting blocks, search-after paging, stored offsets for
highlighting, a second language, synonyms/fuzzy matching. See
[docs/performance.md](docs/performance.md) for why these matter.

## License

MIT
