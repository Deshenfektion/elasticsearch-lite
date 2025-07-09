<?php

declare(strict_types=1);

namespace EsLite\Analysis;

use EsLite\Exception\ConfigurationException;

final class StopWords
{
    private const array ENGLISH = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are',
        'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but',
        'by', 'can', 'cannot', 'could', 'did', 'do', 'does', 'doing', 'down', 'during', 'each',
        'few', 'for', 'from', 'further', 'had', 'has', 'have', 'having', 'he', 'her', 'here',
        'hers', 'herself', 'him', 'himself', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it',
        'its', 'itself', 'just', 'me', 'more', 'most', 'my', 'myself', 'no', 'nor', 'not', 'now',
        'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves',
        'out', 'over', 'own', 'same', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the',
        'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this', 'those',
        'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'we', 'were', 'what',
        'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'would', 'you',
        'your', 'yours', 'yourself', 'yourselves',
    ];

    private array $words;

    public function __construct(array $words)
    {
        $this->words = array_fill_keys(array_map(strtolower(...), $words), true);
    }

    public static function named(string $name): self
    {
        return match (strtolower($name)) {
            'english' => self::english(),
            'none', '' => self::none(),
            default => throw ConfigurationException::unknown('stop word set', $name, ['english', 'none']),
        };
    }

    public static function english(): self
    {
        return new self(self::ENGLISH);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function has(string $word): bool
    {
        return isset($this->words[$word]);
    }

    public function all(): array
    {
        return array_keys($this->words);
    }

    public function count(): int
    {
        return count($this->words);
    }
}
