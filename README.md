# elasticsearch-lite

A small full-text search engine, written to understand how Elasticsearch and Lucene work
underneath the query DSL.

Not a clone. The point is to build the parts that actually do the work — a document
pipeline, an inverted index, a query parser, a ranking model, a highlighter — in PHP,
with no search library to lean on.

## Planned pipeline

```
documents → parser → tokenizer → normaliser → inverted index → ranking → query engine → results
```

## Scope

- Ingest plain text, HTML and JSON documents.
- Analyse text: tokenize, lowercase, fold accents, drop stop words, stem.
- Store term → document postings with frequencies and positions.
- Parse a boolean query language with phrases, field restrictions and wildcards.
- Rank with TF-IDF, then BM25, weighted per field.
- Highlight matches as snippets rather than whole documents.
- Serve it over a small REST API with a plain JavaScript frontend.

## Stack

PHP 8.4, MySQL 8.4, vanilla JavaScript and SCSS. Composer and PHPUnit for the backend,
ESLint and Prettier for the frontend, Docker Compose to run the whole thing.

## Status

Early. Configuration and the storage layer are in place; the analysis chain is next.
