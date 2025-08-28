# Text analysis

Analysis decides what can ever be found. A term that the tokenizer never produced cannot
be searched for, and a term the query analyzer spells differently than the index
analyzer will never match. The rule that makes everything else work is that **the same
chain runs at index time and at query time**.

```
"Lucene's inverted indexes, café"
        │
        ▼  StandardTokenizer          Lucene's | inverted | indexes | café
        ▼  ApostropheFilter           Lucene   | inverted | indexes | café
        ▼  LowercaseFilter            lucene   | inverted | indexes | café
        ▼  AsciiFoldingFilter         lucene   | inverted | indexes | cafe
        ▼  LengthFilter(2, 40)        lucene   | inverted | indexes | cafe
        ▼  StopWordFilter             lucene   | inverted | indexes | cafe
        ▼  StemFilter(porter)         lucen    | invert   | index   | cafe
```

## The tokenizer

`StandardTokenizer` is a single Unicode-aware regular expression:

```
\p{N}+(?:[.,]\p{N}+)*  |  [\p{L}\p{N}]+(?:['’][\p{L}\p{N}]+)*
```

- Letters and digits form a token; everything else is a boundary.
- Numbers keep internal separators, so `8.4` and `1,000` survive as one token.
- Apostrophes stay inside words so that `don't` reaches the filter chain intact.
- Each token carries its position (for phrase queries) and its **byte** start and end
  offsets in the original text (for highlighting).

Offsets are byte offsets, not character offsets, and the highlighter uses `substr`
throughout to match. Token boundaries are always character boundaries, so the two agree;
the passage builder is the only place that has to be careful, and it snaps to whitespace
or walks back off a UTF-8 continuation byte before cutting.

## The filters

| Filter               | What it does                           | What it costs                                                |
| -------------------- | -------------------------------------- | ------------------------------------------------------------ |
| `ApostropheFilter`   | `john's` → `john`, `don't` → `dont`    | Loses the distinction between a possessive and a contraction |
| `LowercaseFilter`    | Case-insensitive matching              | `IT` and `it` collapse, proper nouns lose a signal           |
| `AsciiFoldingFilter` | `café` → `cafe`, `straße` → `strasse`  | German `schön`/`schon` and Spanish `año`/`ano` collapse      |
| `LengthFilter`       | Drops tokens shorter than 2, longer 40 | `a`, `I` and hashes become unsearchable; index stays smaller |
| `StopWordFilter`     | Removes ~130 English function words    | `"to be or not to be"` cannot be found; index shrinks ~30%   |
| `StemFilter`         | Porter stemming to a common root       | `university`/`universe` collapse; recall up, precision down  |

Every one of these is a recall/precision trade, and every one is configurable.
`STOPWORDS=none` and `STEMMER=none` produce a strict index where only exact word forms
match, at the cost of a noticeably larger term dictionary and a search for `indexing` no
longer finding `indexes`.

## Stop words and positions

Removing a stop word leaves a hole in the position sequence rather than closing the gap:

```
the quick brown fox      positions 0 1 2 3
     quick brown fox      positions   1 2 3   after the stop filter
```

Phrase matching compares _relative_ distances, so `"quick brown fox"` (analysed to
relative offsets 0, 1, 2) still lines up with the document's 1, 2, 3. Keeping the hole
is what makes `"bed and breakfast"` work too: the query analyses to `bed` at 0 and
`breakfast` at 2, and the matcher looks for exactly that gap. If gaps were closed, the
query would demand adjacency the document never had.

`PhraseQuery` therefore carries an `offsets` array alongside its terms, and
`PhraseMatcher` uses those offsets instead of assuming `+1` steps.

## Stemming

`PorterStemmer` implements the 1980 algorithm: five steps of suffix rewriting, each
gated by a measure of vowel–consonant sequences. It is not a lemmatiser and does not try
to be.

```
documents  → document      indexing → index      relational → relat
queries    → queri         agreed   → agre       goodness   → good
```

Some of those are not words. That is fine, because the query goes through the same
function: `queri` is the shared key for `query`, `queries` and `querying`. Non-ASCII and
non-alphabetic tokens are returned unchanged, so `straße` and `bm25` pass through
untouched.

`CachingStemmer` wraps it in an LRU. Stemming is pure and the same words repeat
constantly, so the cache hit rate on real text is high; in the benchmark corpus it
removes most of the stemmer cost from indexing.

## Query-time normalisation

Full analysis is wrong for one case: prefix and wildcard terms. Stemming `rank*` would
produce `rank*` → `rank` and then expand against the dictionary as if the user had typed
`rank`, which silently changes the query. `Analyzer::normaliseTerm()` therefore applies
only the filters that implement `TermNormaliser` — apostrophe, lowercase, folding — and
skips the rest.

The visible consequence is that wildcards match **index terms**, which are stems:
`stem*` finds the stem `stem`, and `stemming` is not in the dictionary to be matched.
That is documented behaviour rather than a bug, and it is why the suggester also
completes against stems.

## Fields

Three fields are analysed and indexed, with independent boosts:

| Field   | Id  | Boost | Source                                     |
| ------- | --- | ----- | ------------------------------------------ |
| `title` | 1   | 3.0   | Parsed title or the first line of the body |
| `tags`  | 2   | 2.0   | Tag list, joined with spaces               |
| `body`  | 3   | 1.0   | Full text after parsing                    |

Ids are explicit in `config/app.php` because they are written into every posting row;
renaming a field is free, renumbering one is a reindex.
