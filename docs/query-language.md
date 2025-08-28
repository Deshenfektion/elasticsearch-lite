# Query language

A search box that only accepts bare words wastes the index. The query language is small
enough to fit in a help tooltip and expressive enough to cover the queries people
actually type.

## Cheat sheet

| Query                         | Meaning                                                   |
| ----------------------------- | --------------------------------------------------------- |
| `inverted index`              | Either term; documents with both rank higher              |
| `inverted AND index`          | Both terms required                                       |
| `bm25 OR tfidf`               | Either term, explicitly                                   |
| `+index ranking`              | `index` required, `ranking` optional but boosts the score |
| `index -ranking`              | `index` required, `ranking` prohibited                    |
| `index NOT ranking`           | Same thing                                                |
| `"posting list"`              | Phrase: the terms must be adjacent                        |
| `title:bm25`                  | Term restricted to one field                              |
| `title:"inverted index"`      | Phrase restricted to one field                            |
| `title:(bm25 OR tfidf)`       | Field applies to the whole group                          |
| `rank*`                       | Prefix: every dictionary term starting with `rank`        |
| `st?m`, `to*n`                | Wildcard: `?` is one character, `*` is any run            |
| `ranking AND (bm25 OR tfidf)` | Grouping                                                  |
| `*` or an empty query         | Match everything (browse mode)                            |

Searchable fields are `title`, `tags` and `body`. `category`, `author`, tags-as-filter
and dates are **filters**, not query syntax — they arrive as query-string parameters,
and asking for `category:Guides` is a parse error that names the fields you can use.

## Grammar

```
query    := clause*
clause   := ('+' | '-' | 'NOT' | '!')? primary
primary  := '(' query ')'
          | IDENT ':' (phrase | '(' query ')' | term)
          | phrase
          | term
phrase   := '"' WORD* '"'
term     := WORD ('*' | '?' anywhere makes it a wildcard)
```

`AND` and `OR` are infix operators applied while clauses accumulate: `AND` promotes the
clause before and after it to required, `OR` demotes them to optional. The operator
applied to a bare clause is `search.default_operator` (`or` by default, which is what a
search box normally wants).

Everything is byte-oriented and case-insensitive for the operators: `and`, `And` and
`AND` are the same token, while `and` inside a phrase is just a word.

## Lexer and parser

`Lexer` produces `Word`, `Phrase`, `Colon`, `LeftParen`, `RightParen`, `Plus`, `Minus`,
`And`, `Or`, `Not` and `End` tokens with their source offsets. Punctuation that is not
structural stays inside words, so `e-mail`, `c++` and `8.4` survive as single tokens and
`-` only prohibits when it starts a clause.

`QueryParser` is a recursive-descent parser producing an AST of value objects:

```
+index -rank title:bm25

BooleanQuery
├── MUST      TermQuery(index)
├── MUST_NOT  TermQuery(rank)
└── SHOULD    TermQuery(bm25, field: title)
```

Errors carry a position: an unterminated phrase, an unbalanced parenthesis, an empty
group, a query over 512 bytes or nesting deeper than 12 levels all raise
`QueryParseException`, which the HTTP layer turns into a 400 with `details.position`.

The AST is pure syntax. It holds the words the user typed, not analysed terms, and it
knows nothing about the index. That is what makes `QueryParserTest` a pure unit test.

## Planning and rewriting

`QueryPlanner` turns syntax into something executable:

| Node                    | Rewrite                                                               |
| ----------------------- | --------------------------------------------------------------------- |
| `TermQuery`             | Analysed; one token stays a term, several become a required group     |
| `TermQuery` (stop word) | `MatchNoneQuery`, and the clause is dropped if it was optional        |
| `PhraseQuery`           | Analysed, keeping relative position offsets so stop-word gaps survive |
| `PhraseQuery` (1 token) | Collapses to `TermQuery`                                              |
| `PrefixQuery`           | Dictionary range scan → `ExpandedQuery` of concrete terms             |
| `WildcardQuery`         | `LIKE` over the dictionary, bounded by any literal prefix             |
| `BooleanQuery`          | Children rewritten; single surviving clause unwraps                   |

Expansion is capped at `search.max_expansions` (64). Beyond that the plan is truncated
and `ExpandedQuery::$truncated` says so, because `a*` on a real dictionary is tens of
thousands of terms and one query should not be able to scan the whole index.

Expanded clauses are scored as a disjunction with **coordination disabled**: term
frequency across arbitrary expansions is not comparable, so a document matching four
expansions of `ind*` should not automatically beat one matching two.

The plan also decides whether positions are needed. `PlannedQuery::$needsPositions` is
true only if a phrase survived the rewrite, and it controls whether the `positions` blob
is selected at all.

The rewritten query is returned in every response, which makes the analysis visible:

```json
{
  "query": {
    "parsed": "(inverted index)",
    "rewritten": "(invert index)",
    "terms": ["invert", "index"],
    "positions_loaded": false
  }
}
```

## Execution

`ScorerFactory` maps the rewritten AST onto a scorer tree:

- required clauses → `ConjunctionScorer` (leapfrog, cheapest clause leads)
- required + optional → `RequiredOptionalScorer` (required drives, optional add score)
- optional only → `DisjunctionScorer` (min-heap, coordination factor)
- prohibited → `ExclusionScorer` wrapping the result
- a filter set → `FilteredScorer` wrapping everything
- a term that is not in the dictionary → `EmptyScorer`, and if it was required the whole
  tree collapses to `EmptyScorer` before a single posting is read

`Searcher` then walks the tree once, scoring every match and keeping the best
`from + size` in a bounded min-heap.
