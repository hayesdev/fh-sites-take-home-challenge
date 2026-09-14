<?php
declare(strict_types=1);

namespace PokerHand;

enum Suit: string // enum allows using tryFrom() to validate the suit later, no need for more defensive checks
{
    case Spades = 's';
    case Hearts = 'h';
    case Diamonds = 'd';
    case Clubs = 'c';
}

final class Card
{
    private const RANKS = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8,
        '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14,
    ];

    private function __construct(
        public readonly int $rank,
        public readonly Suit $suit,
    ) {}

    public static function fromString(string $card): self
    {
        // parse from the right here since '10s' is three chars and $card[0] returns '1' for any '10', which breaks
        $suitChar = substr($card, -1);
        $rankChar = substr($card, 0, -1);

        $suit = Suit::tryFrom($suitChar); 
        if (null === $suit || !array_key_exists($rankChar, self::RANKS)) {
            throw new \InvalidArgumentException("Unknown card: '$card'");
        }
        return new self(self::RANKS[$rankChar], $suit);
    }

    public function __toString(): string
    {
        $name = array_search($this->rank, self::RANKS, true);
        return $name . $this->suit->value; 
    }
}

enum HandRank: int // enum here allows using value comparison to evaluate what hand is better
{
    case HighCard      = 1;
    case OnePair       = 2;
    case TwoPair       = 3;
    case ThreeOfAKind  = 4;
    case Straight      = 5;
    case Flush         = 6;
    case FullHouse     = 7;
    case FourOfAKind   = 8;
    case StraightFlush = 9;
    case RoyalFlush    = 10;

    public function label(): string 
    {
        return match ($this) { 
            self::HighCard      => 'High Card',
            self::OnePair       => 'One Pair',
            self::TwoPair       => 'Two Pair',
            self::ThreeOfAKind  => 'Three of a Kind',
            self::Straight      => 'Straight',
            self::Flush         => 'Flush',
            self::FullHouse     => 'Full House',
            self::FourOfAKind   => 'Four of a Kind',
            self::StraightFlush => 'Straight Flush',
            self::RoyalFlush    => 'Royal Flush',
        };
    }

    public function beats(self $other): bool 
    {
        return $this->value > $other->value;
    }
}

final class PokerHand
{
    /**
     * @var Card[]
     */
    private readonly array $cards;
    private readonly HandRank $rank;
    
    /**
     * @throws \InvalidArgumentException on anything that isn't five distinct, valid cards
     */
    public function __construct(string $hand)
    {
      $this->cards = self::parseHand($hand);
      $this->rank = self::classifyHand(
        array_map(fn(Card $c) => $c->rank, $this->cards),
        array_map(fn(Card $c) => $c->suit->value, $this->cards)
      );
    }

    public function getRank(): string 
    {
      return $this->rank->label();
    }

    public function rank(): HandRank 
    {
      return $this->rank;
    }

    public function __toString(): string
    {
      return implode(' ', array_map('strval', $this->cards));
    }

    // ---- Ranking a hand ------
  /**
   * Split, validate, and turn hand string into a Card
   * @return Card[]
   */

  private static function parseHand(string $hand): array
  {
    $handTokens = preg_split('/\s+/', trim($hand), flags: PREG_SPLIT_NO_EMPTY); 

    if (5 !== count($handTokens)) {
      throw new \InvalidArgumentException(
        sprintf("A hand has 5 cards, got %d.", count($handTokens))
      );
    }

    if (5 !== count(array_unique($handTokens))) {
      throw new \InvalidArgumentException(
        "A hand must contain 5 unique cards, got '$hand'."
      );
    }

    return array_map(Card::fromString(...), $handTokens);
  }

  /**
   * One call collapses six of ten ranks into a string that can be ranked:
   * '4,1' '3,2' '3,1,1' '2,2,1' '2,1,1,1' '1,1,1,1,1'
   * @param int[] $ranks
   */
  private static function handShape(array $ranks): string
  {
    $counts = array_count_values($ranks);
    rsort($counts); // in-place sort returns bool - can't inline into implode
    return implode(',', $counts);  
  }

  /**
   * @param string[] $suits
   */
  private static function isFlush(array $suits): bool
  {
    return 1 === count(array_unique($suits));
  }

  /**
   * @param int[] $ranks
   */
  private static function straightHigh(array $ranks): ?int // ?int instead of bool makes royal flush check just `14 === $straight`
  {
    $distinct = array_values(array_unique($ranks));
    sort($distinct);

    if (5 !== count($distinct))            return null;
    if (4 === $distinct[4] - $distinct[0]) return $distinct[4];
    if ([2, 3, 4, 5, 14] === $distinct)    return 5; // the wheel (ace low straight) means 5 must be the high card
    return null;
  }

  private static function classifyHand(array $ranks, array $suits): HandRank 
  {
    $shape = self::handShape($ranks);
    $flush = self::isFlush($suits);
    $straight = self::straightHigh($ranks);

    return match (true) { // first true match arm wins so desc. order IS the rule that a straight flush beats a flush
      $flush && 14   === $straight      => HandRank::RoyalFlush,
      $flush && null !== $straight      => HandRank::StraightFlush,
      '4,1'          === $shape         => HandRank::FourOfAKind,
      '3,2'          === $shape         => HandRank::FullHouse,
      $flush                            => HandRank::Flush,
      null           !== $straight      => HandRank::Straight,
      '3,1,1'        === $shape         => HandRank::ThreeOfAKind,
      '2,2,1'        === $shape         => HandRank::TwoPair,
      '2,1,1,1'      === $shape         => HandRank::OnePair,
      default                           => HandRank::HighCard,
    };
  }
}