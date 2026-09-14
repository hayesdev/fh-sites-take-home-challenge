<?php

declare(strict_types=1);

namespace PokerHand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PokerHandTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRankARoyalFlush()
    {
        $hand = new PokerHand('As Ks Qs Js 10s');
        $this->assertEquals('Royal Flush', $hand->getRank());
    }

    /**
     * @test
     */
    public function itCanRankAPair()
    {
        $hand = new PokerHand('Ah As 10c 7d 6s');
        $this->assertEquals('One Pair', $hand->getRank());
    }

    /**
     * @test
     */
    public function itCanRankTwoPair()
    {
        $hand = new PokerHand('Kh Kc 3s 3h 2d');
        $this->assertEquals('Two Pair', $hand->getRank());
    }

    /**
     * @test
     */
    public function itCanRankAFlush()
    {
        $hand = new PokerHand('Kh Qh 6h 2h 9h');
        $this->assertEquals('Flush', $hand->getRank());
    }

    // Additional tests 

    public static function hands(): array
    {
        return [
            'royal flush'          => ['As Ks Qs Js 10s', 'Royal Flush'],
            'straight flush'       => ['9c 8c 7c 6c 5c',  'Straight Flush'],

            // Ace plays low in the wheel, so the high card is the 5 - which is
            // the only reason this is not a Royal Flush.
            'steel wheel'          => ['5h 4h 3h 2h Ah',  'Straight Flush'],

            // Four tens: '10c' must parse as rank 10, not rank '1'.
            'four of a kind, tens' => ['10c 10d 10h 10s 2d', 'Four of a Kind'],

            'full house'           => ['6s 6h 6d Kc Kh',  'Full House'],
            'flush'                => ['Ah Jh 9h 5h 3h',  'Flush'],
            'wheel straight'       => ['Ah 5c 4d 3s 2h',  'Straight'],
            'broadway straight'    => ['Ah Kc Qd Js 10h', 'Straight'],
            'three of a kind'      => ['7c 7d 7h 2s 5h',  'Three of a Kind'],
            'two pair'             => ['Jc Jd 4h 4s 9c',  'Two Pair'],
            'one pair'             => ['3c 3d 7h Ks 9c',  'One Pair'],
            'high card'            => ['2c 3d 4h 5s 7c',  'High Card'],

            // K-Q-J-9 is one off a run.
            'near straight'        => ['Ah Kc Qd Js 9h',  'High Card'],

            // Ace is high or low, never both.
            'no wrap-around'       => ['Qc Kd Ah 2s 3c',  'High Card'],
        ];
    }

    #[Test]
    #[DataProvider('hands')]
    public function itRanksHands(string $hand, string $expected): void
    {
        self::assertSame($expected, (new PokerHand($hand))->getRank());
    }

    #[Test]
    public function strongerHandsBeatWeakerOnes(): void
    {
        // One hand per rank, weakest first.
        $ladder = array_map(fn(string $h) => new PokerHand($h), [
            'Ad Kc 9h 5s 3d', '6d 6h Ks 9c 2d', 'Jd Jh 4s 4c 9d', '4d 4h 4s 9c 2d',
            '9d 8c 7h 6s 5d', 'As 10s 7s 4s 2s', '3d 3h 3s 8c 8d', '7d 7h 7s 7c 2d',
            '5h 4h 3h 2h Ah', 'As Ks Qs Js 10s',
        ]);

        foreach ($ladder as $i => $strong) {
            foreach ($ladder as $j => $weak) {
                self::assertSame($i > $j, $strong->beats($weak), "{$strong->getRank()} vs {$weak->getRank()}");
            }
        }
    }

    #[Test]
    public function equalRanksDoNotBeatEachOther(): void
    {
        // Strict >. Breaking this tie needs kickers, which aren't asked for in the challenge.
        $a = new PokerHand('6d 6h Ks 9c 2d');
        $b = new PokerHand('8d 8h Qs 7c 3d');

        self::assertFalse($a->beats($b));
        self::assertFalse($b->beats($a));
    }

    #[Test]
    public function itExposesTheRankAsAnEnumAndAsAString(): void
    {
        $hand = new PokerHand('Jc Jd 4h 4s 9c');

        self::assertSame(HandRank::TwoPair, $hand->rank());
        self::assertSame('Two Pair', $hand->getRank());
    }

    #[Test]
    public function itPrintsTheCardsBack(): void
    {
        // Round-tripping '10c' proves __toString inverts the rank table.
        self::assertSame('10c 10d 10h 10s 2d', (string) new PokerHand('10c 10d 10h 10s 2d'));
        self::assertSame('As Ks Qs Js 10s', (string) new PokerHand('  As   Ks Qs  Js 10s  '));
    }

    public static function oddSpacing(): array
    {
        return [
            'double spaces'        => ['As  Ks   Qs Js 10s'],
            'leading and trailing' => ['  As Ks Qs Js 10s  '],
        ];
    }

    #[Test]
    #[DataProvider('oddSpacing')]
    public function itAcceptsAnyWhitespaceBetweenCards(string $hand): void
    {
        // explode(' ') leaves empty tokens here and rejects a legal hand.
        self::assertSame('Royal Flush', (new PokerHand($hand))->getRank());
    }

    public static function malformed(): array
    {
        return [
            'duplicate card'  => ['Ah Ah Kc Qd Js',    'unique'],
            'only four cards' => ['Ah Kc Qd Js',       '5 cards'],
            'six cards'       => ['Ah Kc Qd Js 2h 3h', '5 cards'],
            'empty string'    => ['',                  '5 cards'],
            'unknown rank'    => ['Zh Kc Qd Js 2h',    "Unknown card: 'Zh'"],
            'there is no 1'   => ['1h Kc Qd Js 2h',    "Unknown card: '1h'"],
            'unknown suit'    => ['Ax Kc Qd Js 2h',    "Unknown card: 'Ax'"],
        ];
    }

    #[Test]
    #[DataProvider('malformed')]
    public function itRejectsMalformedHands(string $hand, string $because): void
    {
        // Asserting the message too: a duplicate must fail the duplicate check,
        // not fall through to the card-count check.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($because);

        new PokerHand($hand);
    }
}
