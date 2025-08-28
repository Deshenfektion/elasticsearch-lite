# Ranking

Two scoring models ship, both implementing the same three-method interface, both
selectable at runtime with `RANKING_MODEL`. The default is BM25.

```php
interface ScoringModel
{
    public function idf(int $documentFrequency, int $collectionSize): float;
    public function termScore(float $idf, int $termFrequency, int $fieldLength, float $averageFieldLength): float;
    public function parameters(): array;
}
```

## Why term frequency alone fails

A document that says _database_ forty times is not forty times more relevant than one
that says it once, and a term that appears in every document tells you nothing at all.
Any usable model has to answer three questions: how much does repetition count, how much
does rarity count, and how much does document length count.

## TF-IDF

The Lucene-style practical variant:

```
idf(t)          = 1 + ln( N / (df(t) + 1) )
tf(t, f)        = sqrt(freq(t, f))
norm(f)         = 1 / sqrt(length(f))
score(t, f)     = tf * idf² * norm * boost(field)
```

IDF is squared because in the vector-space derivation it appears on both the query and
the document side. The square root on term frequency is the damping: the second
occurrence adds a lot, the ninth adds little. `1 / sqrt(length)` is the length
normalisation, which is blunt — it cannot be tuned, and on a collection of mixed short
titles and long articles it over-rewards the short ones.

## BM25

```
idf(t)      = ln( 1 + (N - df(t) + 0.5) / (df(t) + 0.5) )
score(t, f) = idf · ( freq · (k1 + 1) ) / ( freq + k1 · (1 - b + b · length/avgLength) )
```

with `k1 = 1.2` and `b = 0.75` by default.

- `k1` controls saturation. As `freq` grows the term contribution approaches
  `idf · (k1 + 1)`, so there is a ceiling: the tenth occurrence is worth far less than
  the second. With `k1 = 0` term frequency stops mattering entirely.
- `b` controls length normalisation. At `b = 0` length is ignored; at `b = 1` the score
  is fully divided by relative length. `0.75` is the value that has survived twenty-five
  years of TREC runs, and it is the right default precisely because it is not tuned to
  this corpus.
- The IDF form is smooth and never negative for sane inputs, which matters when a term
  appears in most of the collection.

`ScoringModelTest` asserts these properties rather than fixed numbers: IDF falls as
document frequency rises, the score rises with term frequency, the increments shrink
(saturation), longer fields score lower, and `b = 0` makes length irrelevant.

## Field weighting

A term in a title usually states what a document is about. Postings are stored per
field, so a document's score for one term is the sum over the fields it appeared in:

```
score(t, d) = Σ_fields  model.termScore(idf, freq, fieldLength, avgFieldLength) · boost(field)
```

| Field   | Boost | Reasoning                               |
| ------- | ----- | --------------------------------------- |
| `title` | 3.0   | Strongest signal of topic               |
| `tags`  | 2.0   | Curated, deliberately chosen vocabulary |
| `body`  | 1.0   | Baseline                                |

Boosts interact with length normalisation in a way that catches people out: titles are
short, so their normalised term frequency is already high before the boost is applied. A
boost of 10 on `title` will bury genuine body matches. The defaults are conservative for
that reason, and all three are environment variables so they can be tuned against judged
queries rather than taste.

## Combining clauses

- **Required clauses** (`AND`, `+`) sum their scores. A document that matched both terms
  of a two-term conjunction scores the sum of both contributions.
- **Optional clauses** (`OR`, default) sum the scores of the clauses that matched, then
  multiply by a coordination factor `matched / total`. Without it, one strong term match
  can outrank a document that matched every term; with it, matching more of the query is
  rewarded. It can be disabled with `ranking.coordination`.
- **Prohibited clauses** contribute nothing; they only remove documents.
- **Phrases** score like a single term whose IDF is the sum of the member IDFs and whose
  frequency is the number of phrase occurrences, multiplied by `ranking.phrase_boost`
  (2.0). A document containing the exact phrase should outrank one that merely contains
  both words, and this is where that ordering comes from.
- **Expanded clauses** (from `prefix*` or wildcards) are a disjunction with coordination
  off.

## Explanations

Every hit can carry the arithmetic behind its score. `GET /search?q=...&explain=1`
returns a tree per hit:

```json
{
  "description": "sum of optional clauses",
  "value": 14.987,
  "details": [
    {
      "description": "term \"invert\"",
      "value": 8.211,
      "details": [
        {
          "description": "idf, 3 documents contain \"invert\" out of 20",
          "value": 2.014
        },
        {
          "description": "field \"title\", tf 1, length 5, boost 3.00",
          "value": 5.371
        },
        { "description": "field \"body\", tf 2, length 96, boost 1.00", "value": 2.84 }
      ]
    },
    { "description": "coordination, 2 of 2 clauses matched", "value": 1.0 }
  ]
}
```

Explanations are built by a second pass over a fresh scorer tree positioned on that one
document, so the hot path never pays for them. Nine times out of ten the surprise they
reveal is mundane: a stop word was dropped, the stemmer collapsed two different words,
or a field boost is fighting length normalisation. The UI exposes the same tree behind a
"Show ranking details" toggle.

## Sorting by something other than relevance

`sort=newest` and `sort=oldest` bypass scoring: the engine collects the matching
document ids, then orders them by `published_at` in SQL with nulls last. Mixing a date
sort with relevance scores would be misleading, so hits come back with a score of zero
and the ordering is entirely the sort field.
