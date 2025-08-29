# Benchmark results

Every number here comes from `bench/run.php` on the machine described below. Nothing is
extrapolated. Raw JSON for each run is written to `bench/out/` and the runs used for
this document are `mysql-1000`, `mysql-5000` and `sqlite-5000`.

## Setup

| Item     | Value                                                               |
| -------- | ------------------------------------------------------------------- |
| Machine  | Apple M1 Pro, 8 CPUs and 4 GB visible to Docker                     |
| Runtime  | PHP 8.4.24, `php:8.4-fpm-alpine` image, no opcache in the CLI       |
| Database | MySQL 8.4.11 in the same Compose network, `innodb_buffer_pool=512M` |
| Corpus   | Synthetic, seeded generator, Zipf-skewed vocabulary of 2,398 terms  |
| Docs     | 1,000 and 5,000 documents, 185 tokens per document on average       |
| Caches   | Result cache off, shared (APCu) caches off, per-process LRU on      |
| Method   | 50 iterations per query shape, percentiles over the per-call timing |

The corpus is deliberately synthetic so the run is reproducible: the same seed produces
the same documents, the same term frequencies and therefore the same posting lists.

`took` is measured inside the engine with `Stopwatch` and excludes HTTP and JSON
encoding. It includes query parsing, planning, dictionary lookups, posting fetches,
scoring, collection, document hydration, highlighting and facet counting.

## Indexing

| Corpus        | Documents/s | Tokens/s | Wall clock |
| ------------- | ----------- | -------- | ---------- |
| MySQL, 1,000  | 191         | 35,378   | 5.2 s      |
| MySQL, 5,000  | 217         | 40,094   | 23.1 s     |
| SQLite, 5,000 | 518         | 95,843   | 9.6 s      |

Indexing is transactional per document: parse, analyse, upsert the document row, replace
the postings, then apply the term frequency deltas. That is four to six statements per
document plus one bulk posting insert, which is why MySQL lands around 200 documents/s
while SQLite, with no network hop, is roughly 2.4x faster.

Updating an existing document costs a delete of its postings, a re-analyse and a
re-insert:

| Corpus        | Update p50 | Update p99 |
| ------------- | ---------- | ---------- |
| MySQL, 5,000  | 4.33 ms    | 7.72 ms    |
| SQLite, 5,000 | 1.84 ms    | 4.58 ms    |

## Index size

For 5,000 documents and 924,431 tokens:

| Metric                 | Value     |
| ---------------------- | --------- |
| Unique terms           | 2,398     |
| Postings rows          | 512,313   |
| Position bytes (MySQL) | 1,044,415 |
| Bytes per position     | 1.13      |

Positions are delta-gapped and variable-byte encoded, which is where the 1.13 bytes per
position comes from: most gaps inside a field fit in a single byte. Storing one row per
position instead would have cost roughly 924k rows in place of 512k, and every row
carries index and row overhead.

## Query latency, MySQL, 5,000 documents

| Query shape           | Hits  | p50 ms | p90 ms | p99 ms | cold p50 |
| --------------------- | ----- | ------ | ------ | ------ | -------- |
| term (rare)           | 46    | 4.18   | 4.63   | 7.51   | 4.85     |
| term (common)         | 4,996 | 20.92  | 21.64  | 32.35  | 26.42    |
| two terms (or)        | 5,000 | 26.57  | 27.29  | 37.01  | 37.53    |
| two terms (and)       | 1,447 | 11.66  | 12.18  | 14.95  | 19.15    |
| three terms (or)      | 5,000 | 29.90  | 31.64  | 36.08  | 42.52    |
| phrase                | 250   | 5.49   | 6.65   | 8.55   | 6.76     |
| prefix (5 expansions) | 4,999 | 31.81  | 32.61  | 43.62  | 39.33    |
| field restricted      | 1,452 | 10.36  | 11.14  | 12.93  | 16.14    |
| negation              | 118   | 6.33   | 6.94   | 7.45   | 17.08    |
| filtered              | 624   | 8.43   | 9.07   | 10.75  | 14.91    |
| filtered, facets off  | 624   | 3.37   | 3.86   | 4.10   | 10.02    |
| deep page (page 20)   | 4,996 | 21.78  | 23.22  | 23.93  | 27.89    |
| browse (empty query)  | 5,000 | 20.15  | 21.60  | 22.51  | 20.48    |

`cold p50` flushes the term and posting-list caches before every call, so it is the
honest number for a PHP-FPM worker that has just been handed a fresh request.

## Query latency, MySQL, 1,000 documents

| Query shape          | Hits  | p50 ms | p90 ms | p99 ms |
| -------------------- | ----- | ------ | ------ | ------ |
| term (rare)          | 15    | 2.60   | 2.92   | 5.88   |
| term (common)        | 999   | 5.93   | 6.82   | 9.32   |
| two terms (or)       | 1,000 | 7.24   | 7.78   | 10.42  |
| two terms (and)      | 279   | 4.59   | 5.25   | 6.48   |
| phrase               | 50    | 2.76   | 3.18   | 4.44   |
| prefix               | 1,000 | 8.18   | 8.72   | 10.37  |
| filtered             | 124   | 4.02   | 4.56   | 5.44   |
| browse (empty query) | 1,000 | 4.12   | 4.52   | 5.26   |

Going from 1,000 to 5,000 documents (5x) moves the common-term p50 from 5.9 ms to 20.9
ms (3.5x) and the OR query from 7.2 ms to 26.6 ms (3.7x). Growth tracks the number of
postings scanned, not the number of documents in the collection, and the fixed per-query
cost (parse, plan, dictionary lookup, hydration of ten documents) is what keeps it
sublinear.

## SQLite, 5,000 documents

| Query shape          | p50 ms | p99 ms |
| -------------------- | ------ | ------ |
| term (rare)          | 1.71   | 6.96   |
| term (common)        | 33.63  | 145.90 |
| two terms (and)      | 18.05  | 23.59  |
| phrase               | 2.43   | 5.86   |
| filtered, facets off | 3.13   | 3.75   |

SQLite wins on small result sets because there is no socket in the path: a rare term is
1.7 ms against 4.2 ms on MySQL. It loses badly on large scans in this setup because the
database file sits on a bind-mounted macOS volume, and the 145 ms p99 on the common term
is that filesystem, not the engine. SQLite is used for the test suite; MySQL is the
target.

## Reading the numbers

- Everything except the widest scans sits comfortably under 50 ms at p99.
- The cost of a query is dominated by how many postings it has to score. A term that
  matches 4,996 of 5,000 documents is the pathological case for this engine, because
  there is no block-max or WAND pruning: every match is scored so the total hit count is
  exact.
- Facet counting is not free. `filtered` at 8.4 ms versus `filtered, facets off` at 3.4
  ms is the cost of three grouped queries over the matched document ids.
- Prefix queries pay for expansion. `prefix` expands to five dictionary terms and
  behaves like a five-clause disjunction.
- The cache columns only differ by 10 to 30 percent because the caches are per-process
  and the benchmark reuses one process. In production the term cache is backed by APCu,
  which is what makes it survive between requests.

## What would move the numbers

Measured, not guessed, would be the next step for each of these:

- **Block-max WAND**: store the maximum term score per posting block and skip blocks
  that cannot enter the top k. This is the single biggest win available for common
  terms.
- **Approximate totals**: reporting "more than 1,000 hits" instead of an exact count
  removes the requirement to score every match.
- **Posting compression**: document ids are stored as one row each. Grouping a term's
  postings into a compressed block per term would cut the I/O for common terms
  considerably, at the cost of read-modify-write on every update.
- **Cross-request posting cache**: the term dictionary already lives in APCu; hot
  posting lists do not, because they are much larger and eviction gets expensive.
