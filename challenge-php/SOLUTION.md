# Notes on this solution

## Running it

```
composer install
composer test-poker
```

31 tests pass: the 4 shipped with the challenge, unchanged, plus 27 I added.
`vendor/bin/php-cs-fixer check` reports no fixes needed against the `@PER-CS`
config in this repo.

## Approach

The ten hand ranks look like ten separate checks, but they overlap — a straight
flush satisfies three of them at once. Writing ten detectors means writing the
overlap rules by hand and getting the precedence right in ten places.

So instead I compute three summaries of a hand, then read the rank off them:

| Summary | Returns | Covers |
| --- | --- | --- |
| `handShape()` | `'4,1'`, `'3,2'`, `'2,2,1'` … | six of the ten ranks |
| `isFlush()` | `bool` | flush |
| `straightHigh()` | `?int` — the high card, or null | straight |

`classifyHand()` is then a single `match (true)` with the arms in strength
order. `match (true)` takes the first arm that is true, so the ordering of the
arms *is* the rule that a straight flush outranks a flush. There is no
precedence logic anywhere else.

## Decisions worth calling out

**`straightHigh()` returns `?int`, not `bool`.** Returning the high card is what
makes the royal flush check `14 === $straight` instead of a separate "is there an
ace" branch. It is also what keeps the wheel correct: `5h 4h 3h 2h Ah` returns 5,
not 14, because the ace plays low there — so it comes out a Straight Flush rather
than a Royal Flush.

**`HandRank` is an int-backed enum.** The backing value is the strength order,
so comparing two hands is one `>`. That is the whole of `beats()`.

**Cards are parsed from the right.** `'10s'` is three characters, so `$card[0]`
returns `'1'` and breaks on every ten. `substr($card, 0, -1)` and
`substr($card, -1)` do not.

**The work happens once, in the constructor.** `getRank()` returns a stored
value rather than recomputing.

**Input is case sensitive.** The rank table is also the validator, so `'as'` is
rejected rather than silently accepted. Deliberate, and there is a test for it.

**Two hands of the same rank: `beats()` returns false in both directions.**
Separating them needs kickers, which the brief does not ask for.

## Things the brief did not ask for

The brief does not mention error handling. I added it because a ranker that
silently returns "Flush" for four cards or a hand with two aces of spades is
worse than one that refuses: the caller cannot tell a real answer from a broken
one. Anything that is not five distinct, valid cards throws
`InvalidArgumentException`, and the message says which rule failed.

`beats()` and `__toString()` are there because they are the two things I would
reach for first if this were real, and both are one line given the enum.

## Where I would extend it

**Seven-card hands.** Rank the best of the 21 five-card subsets and take the
highest. The three summaries and `classifyHand()` do not change — it is a new
method that loops and keeps the maximum, which works because `HandRank` is
already comparable.

**Tiebreakers within a rank.** Compare kickers when the two `HandRank`s are
equal. The card ranks are already on the object, so this is a change to
`beats()` only.

## About the tests

The added cases are the edge cases I thought were most likely to be gotten wrong:

- the wheel, suited and unsuited — the ace plays low, so `5h 4h 3h 2h Ah` is a
  Straight Flush and not a Royal Flush
- hands full of tens, which catch a parser that reads `'10c'` as rank 1
- `Qc Kd Ah 2s 3c` — the ace does not wrap around, so this is High Card
- `Ah Kc Qd Js 9h` — one off a straight
- hands separated by extra spaces, which must still be accepted;
  `explode(' ')` would reject them
- malformed hands so duplicate fails the duplicate check rather than the card-count check
- every hand beating every weaker hand and nothing else, across all ten ranks
