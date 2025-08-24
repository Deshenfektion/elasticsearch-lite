<?php

declare(strict_types=1);

namespace EsLite\Bench;

use EsLite\Document\SourceDocument;

final class CorpusGenerator
{
    public const string PHRASE = 'posting list';

    private const array SYLLABLES = [
        'ba', 'be', 'bi', 'bo', 'ca', 'ce', 'ci', 'da', 'de', 'do', 'fa', 'fe', 'fi', 'ga', 'ge',
        'ka', 'ke', 'ki', 'la', 'le', 'li', 'lo', 'ma', 'me', 'mi', 'na', 'ne', 'ni', 'no', 'pa',
        'pe', 'pi', 'ra', 're', 'ri', 'ro', 'sa', 'se', 'si', 'so', 'ta', 'te', 'ti', 'to', 'va',
        'vi', 'za', 'ze',
    ];

    private const array CATEGORIES = [
        'Indexing', 'Ranking', 'Analysis', 'Storage', 'Performance', 'Query Parsing', 'Highlighting', 'Operations',
    ];

    private const array AUTHORS = [
        'Ada Lovelace', 'Grace Hopper', 'Alan Kay', 'Barbara Liskov', 'Edsger Dijkstra', 'Frances Allen',
        'Ken Thompson', 'Radia Perlman', 'Leslie Lamport', 'Jean Bartik', 'Tony Hoare', 'Karen Sparck Jones',
    ];

    private array $vocabulary = [];

    private array $cumulative = [];

    private array $tags = [];

    public function __construct(
        private readonly int $vocabularySize = 2400,
        private readonly float $skew = 1.08,
        private readonly int $seed = 20250801,
    ) {
        $this->build();
    }

    public function documents(int $count): iterable
    {
        mt_srand($this->seed + 1);

        for ($index = 1; $index <= $count; $index++) {
            yield $this->document($index);
        }
    }

    public function vocabulary(): array
    {
        return $this->vocabulary;
    }

    public function termAtRank(int $rank): string
    {
        return $this->vocabulary[min(max($rank, 0), count($this->vocabulary) - 1)];
    }

    public function categories(): array
    {
        return self::CATEGORIES;
    }

    private function document(int $index): SourceDocument
    {
        $titleWords = $this->words(mt_rand(5, 9));
        $bodyWords = $this->words(mt_rand(90, 260));

        if ($index % 20 === 0) {
            array_splice($bodyWords, mt_rand(0, max(0, count($bodyWords) - 3)), 0, explode(' ', self::PHRASE));
        }

        $tags = [];

        for ($tag = 0, $tagCount = mt_rand(1, 4); $tag < $tagCount; $tag++) {
            $tags[] = $this->tags[mt_rand(0, count($this->tags) - 1)];
        }

        return SourceDocument::fromArray([
            'id' => sprintf('bench-%06d', $index),
            'media_type' => 'text/plain',
            'title' => ucfirst(implode(' ', $titleWords)),
            'content' => implode(' ', $bodyWords) . '.',
            'author' => self::AUTHORS[$index % count(self::AUTHORS)],
            'category' => self::CATEGORIES[$index % count(self::CATEGORIES)],
            'tags' => array_values(array_unique($tags)),
            'published_at' => sprintf('2024-%02d-%02d', mt_rand(1, 12), mt_rand(1, 28)),
        ]);
    }

    private function words(int $count): array
    {
        $words = [];

        for ($index = 0; $index < $count; $index++) {
            $words[] = $this->pick();
        }

        return $words;
    }

    private function pick(): string
    {
        $target = mt_rand() / mt_getrandmax() * end($this->cumulative);
        $low = 0;
        $high = count($this->cumulative) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->cumulative[$middle] < $target) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $this->vocabulary[$low];
    }

    private function build(): void
    {
        mt_srand($this->seed);
        $seen = [];

        while (count($seen) < $this->vocabularySize) {
            $word = '';

            for ($syllable = 0, $length = mt_rand(2, 4); $syllable < $length; $syllable++) {
                $word .= self::SYLLABLES[mt_rand(0, count(self::SYLLABLES) - 1)];
            }

            $seen[$word] = true;
        }

        $this->vocabulary = array_keys($seen);
        $running = 0.0;

        foreach ($this->vocabulary as $rank => $word) {
            $running += 1.0 / (($rank + 1) ** $this->skew);
            $this->cumulative[] = $running;
        }

        $this->tags = array_slice($this->vocabulary, 0, 30);
    }
}
