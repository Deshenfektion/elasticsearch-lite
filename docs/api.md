# HTTP API

JSON in, JSON out. nginx forwards `/api/*` to PHP-FPM; the `/api` prefix is optional
when calling the front controller directly, which is what the test suite does.

```
POST   /documents        index or update one document, or a batch
GET    /documents/{id}   fetch the stored document
DELETE /documents/{id}   remove it from the index
POST   /reindex          rebuild the index from the stored documents
GET    /search           run a query
GET    /suggest          term completions and popular queries
GET    /statistics       index, analysis, ranking and cache statistics
GET    /health           readiness checks
```

## POST /documents

```bash
curl -X POST localhost:8080/api/documents -H 'content-type: application/json' -d '{
  "id": "inverted-index-basics",
  "media_type": "text/plain",
  "title": "How an inverted index actually works",
  "content": "An inverted index maps every term to the list of documents that contain it.",
  "author": "Deshen Rao",
  "category": "Information Retrieval",
  "tags": ["inverted index", "postings"],
  "published_at": "2025-07-04"
}'
```

| Field                       | Required | Notes                                                                    |
| --------------------------- | -------- | ------------------------------------------------------------------------ |
| `id`                        | yes      | External id, unique, up to 191 characters                                |
| `content`                   | yes      | Raw document; may be an object for `application/json`                    |
| `media_type`                | no       | `text/plain` (default), `text/markdown`, `text/html`, `application/json` |
| `title`                     | no       | Overrides whatever the parser extracted                                  |
| `author`, `category`, `url` | no       | Stored, filterable                                                       |
| `tags`                      | no       | Array or comma-separated string; indexed **and** filterable              |
| `published_at`              | no       | Any date PHP can parse                                                   |

```json
{
  "id": "inverted-index-basics",
  "status": "created",
  "tokens": 96,
  "terms": 61,
  "took_ms": 7.412
}
```

`status` is `created`, `updated` or `unchanged`. `unchanged` means the content checksum
matched and nothing was rewritten, so re-posting a whole corpus is cheap and idempotent.
`201` for a create, `200` otherwise.

Batches use the same endpoint, up to 500 documents per request:

```json
{
  "documents": [
    { "id": "a", "content": "..." },
    { "id": "b", "content": "..." }
  ]
}
```

```json
{
  "indexed": 2,
  "counts": { "created": 2, "updated": 0, "unchanged": 0 },
  "results": [
    { "id": "a", "status": "created", "tokens": 12, "terms": 9, "took_ms": 1.9 }
  ]
}
```

## GET /search

```bash
curl 'localhost:8080/api/search?q=inverted+index&size=10&category=Guides&explain=1'
```

| Parameter                   | Default     | Notes                                           |
| --------------------------- | ----------- | ----------------------------------------------- |
| `q`                         | empty       | Query language; empty browses everything        |
| `page`, `size`              | 1, 10       | `size` capped at `search.max_size` (100)        |
| `from`                      | 0           | Alternative to `page`                           |
| `sort`                      | `relevance` | `relevance`, `newest`, `oldest`                 |
| `category`, `author`, `tag` | none        | Repeatable or comma-separated; AND across kinds |
| `match_all_tags`            | `0`         | Require every tag rather than any               |
| `from`, `to` (dates)        | none        | `published_at` range, inclusive                 |
| `highlight`                 | `1`         | Snippets per field                              |
| `facets`                    | `1`         | Facet counts over the matched set               |
| `explain`                   | `0`         | Per-hit score breakdown                         |

```json
{
  "total": 10,
  "max_score": 14.9874,
  "page": 1,
  "size": 10,
  "pages": 1,
  "took_ms": 11.314,
  "cached": false,
  "query": {
    "parsed": "(inverted index)",
    "rewritten": "(invert index)",
    "terms": ["invert", "index"],
    "positions_loaded": false,
    "filters": { "category": ["Guides"] },
    "sort": "relevance"
  },
  "facets": {
    "categories": { "Information Retrieval": 3, "Indexing": 2 },
    "tags": { "postings": 2 },
    "authors": { "Deshen Rao": 3 },
    "truncated": false
  },
  "hits": [
    {
      "id": "inverted-index-basics",
      "media_type": "text/plain",
      "title": "How an inverted index actually works",
      "url": null,
      "author": "Deshen Rao",
      "category": "Information Retrieval",
      "tags": ["inverted index", "postings"],
      "published_at": "2025-07-04T00:00:00+00:00",
      "token_count": 96,
      "indexed_at": "2025-08-24T09:12:44+00:00",
      "score": 14.9874,
      "highlights": {
        "title": ["How an <mark>inverted</mark> <mark>index</mark> actually works"],
        "body": ["An <mark>inverted</mark> <mark>index</mark> maps every term … "]
      }
    }
  ]
}
```

Highlight fragments are HTML-escaped before the tags are inserted, so they are safe to
write into the DOM. `facets.truncated` is true when more than 5,000 documents matched
and the counts were computed over a capped sample.

## GET /suggest

```bash
curl 'localhost:8080/api/suggest?q=ind&size=8'
```

```json
{
  "prefix": "ind",
  "terms": [{ "term": "index", "documents": 10, "query": "index" }],
  "queries": [{ "query": "inverted index", "searches": 4 }]
}
```

`terms` completes the word being typed from the term dictionary, ordered by document
frequency, so every suggestion leads to results. `queries` completes the whole intent
from the search log, counting only queries that returned hits. Prefixes shorter than
`suggest.min_prefix` (2) return empty lists rather than scanning the dictionary.

## GET /statistics

```json
{
  "index": {
    "documents": 5000,
    "terms": 2398,
    "postings": 512313,
    "tokens": 924431,
    "tokens_per_posting": 1.8
  },
  "fields": {
    "title": { "id": 1, "boost": 3.0, "documents": 5000, "average_length": 6.1 }
  },
  "analysis": {
    "tokenizer": "standard",
    "filters": [
      "apostrophe",
      "lowercase",
      "asciifolding",
      "length",
      "stop",
      "stem:porter"
    ]
  },
  "ranking": {
    "model": "bm25",
    "parameters": { "k1": 1.2, "b": 0.75 },
    "phrase_boost": 2.0,
    "coordination": true
  },
  "facets": { "categories": { "Guides": 2 }, "tags": { "index": 2 } },
  "searches": {
    "total": 128,
    "average_ms": 9.4,
    "slowest_ms": 41.2,
    "empty_results": 7
  },
  "caches": { "terms": { "hits": 84, "misses": 12, "hit_ratio": 0.875 } }
}
```

## GET /health

`200` when everything is ready, `503` otherwise, which is what the container health
check uses.

```json
{
  "status": "ok",
  "driver": "mysql",
  "checks": {
    "database": { "ok": true },
    "migrations": { "ok": true, "pending": [] },
    "index": { "ok": true, "documents": 20, "terms": 583 }
  },
  "took_ms": 1.996
}
```

## Errors

```json
{
  "error": {
    "type": "query_parse_error",
    "message": "Unterminated quoted phrase at position 0.",
    "details": { "position": 0 }
  }
}
```

| Status | `type`                                                   | Cause                                     |
| ------ | -------------------------------------------------------- | ----------------------------------------- |
| 400    | `query_parse_error`, `invalid_request`, `malformed_json` | Bad syntax or missing body                |
| 404    | `route_not_found`, `document_not_found`                  | Unknown route or document                 |
| 405    | `method_not_allowed`                                     | Wrong verb; `details.allowed` lists verbs |
| 413    | `payload_too_large`                                      | Body over `api.max_body_bytes`            |
| 415    | `unsupported_media_type`                                 | No parser for that media type             |
| 422    | `invalid_document`, `document_parse_error`               | Valid JSON, unusable document             |
| 500    | `server_error`, `configuration_error`                    | Bug; message hidden outside `local`       |
| 503    | `storage_unavailable`                                    | Database unreachable                      |

Every response carries `access-control-allow-origin` (configurable via `CORS_ORIGIN`)
and `x-content-type-options: nosniff`. `OPTIONS` on any route returns `204` with the
CORS headers.
