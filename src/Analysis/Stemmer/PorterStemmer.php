<?php

declare(strict_types=1);

namespace EsLite\Analysis\Stemmer;

final class PorterStemmer implements Stemmer
{
    private const array VOWELS = ['a' => true, 'e' => true, 'i' => true, 'o' => true, 'u' => true];

    private const array STEP2 = [
        'ational' => 'ate',
        'tional' => 'tion',
        'enci' => 'ence',
        'anci' => 'ance',
        'izer' => 'ize',
        'bli' => 'ble',
        'alli' => 'al',
        'entli' => 'ent',
        'eli' => 'e',
        'ousli' => 'ous',
        'ization' => 'ize',
        'ation' => 'ate',
        'ator' => 'ate',
        'alism' => 'al',
        'iveness' => 'ive',
        'fulness' => 'ful',
        'ousness' => 'ous',
        'aliti' => 'al',
        'iviti' => 'ive',
        'biliti' => 'ble',
        'logi' => 'log',
    ];

    private const array STEP3 = [
        'icate' => 'ic',
        'ative' => '',
        'alize' => 'al',
        'iciti' => 'ic',
        'ical' => 'ic',
        'ful' => '',
        'ness' => '',
    ];

    private const array STEP4 = [
        'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant', 'ement', 'ment', 'ent',
        'ion', 'ou', 'ism', 'ate', 'iti', 'ous', 'ive', 'ize',
    ];

    private readonly array $step2;

    private readonly array $step3;

    private readonly array $step4;

    public function __construct()
    {
        $this->step2 = $this->byLengthDescending(self::STEP2);
        $this->step3 = $this->byLengthDescending(self::STEP3);
        $this->step4 = $this->byLengthDescending(array_fill_keys(self::STEP4, ''));
    }

    public function name(): string
    {
        return 'porter';
    }

    public function stem(string $word): string
    {
        if (strlen($word) < 3 || preg_match('/^[a-z]+$/', $word) !== 1) {
            return $word;
        }

        $stem = $this->step1a($word);
        $stem = $this->step1b($stem);
        $stem = $this->step1c($stem);
        $stem = $this->replace($stem, $this->step2, 0);
        $stem = $this->replace($stem, $this->step3, 0);
        $stem = $this->step4($stem);
        $stem = $this->step5a($stem);

        return $this->step5b($stem);
    }

    private function step1a(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'sses') => substr($word, 0, -2),
            str_ends_with($word, 'ies') => substr($word, 0, -2),
            str_ends_with($word, 'ss') => $word,
            str_ends_with($word, 's') => substr($word, 0, -1),
            default => $word,
        };
    }

    private function step1b(string $word): string
    {
        if (str_ends_with($word, 'eed')) {
            return $this->measure(substr($word, 0, -3)) > 0 ? substr($word, 0, -1) : $word;
        }

        $stem = null;

        if (str_ends_with($word, 'ed')) {
            $candidate = substr($word, 0, -2);
            $stem = $this->containsVowel($candidate) ? $candidate : null;
        } elseif (str_ends_with($word, 'ing')) {
            $candidate = substr($word, 0, -3);
            $stem = $this->containsVowel($candidate) ? $candidate : null;
        }

        if ($stem === null) {
            return $word;
        }

        if (str_ends_with($stem, 'at') || str_ends_with($stem, 'bl') || str_ends_with($stem, 'iz')) {
            return $stem . 'e';
        }

        if ($this->endsWithDoubleConsonant($stem) && !in_array(substr($stem, -1), ['l', 's', 'z'], true)) {
            return substr($stem, 0, -1);
        }

        if ($this->measure($stem) === 1 && $this->endsWithCvc($stem)) {
            return $stem . 'e';
        }

        return $stem;
    }

    private function step1c(string $word): string
    {
        if (!str_ends_with($word, 'y')) {
            return $word;
        }

        $stem = substr($word, 0, -1);

        return $this->containsVowel($stem) ? $stem . 'i' : $word;
    }

    private function step4(string $word): string
    {
        foreach ($this->step4 as $suffix => $ignored) {
            if (!str_ends_with($word, $suffix)) {
                continue;
            }

            $stem = substr($word, 0, -strlen($suffix));

            if ($this->measure($stem) <= 1) {
                return $word;
            }

            if ($suffix === 'ion' && !in_array(substr($stem, -1), ['s', 't'], true)) {
                return $word;
            }

            return $stem;
        }

        return $word;
    }

    private function step5a(string $word): string
    {
        if (!str_ends_with($word, 'e')) {
            return $word;
        }

        $stem = substr($word, 0, -1);
        $measure = $this->measure($stem);

        if ($measure > 1 || ($measure === 1 && !$this->endsWithCvc($stem))) {
            return $stem;
        }

        return $word;
    }

    private function step5b(string $word): string
    {
        if (str_ends_with($word, 'll') && $this->measure($word) > 1) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function replace(string $word, array $suffixes, int $minMeasure): string
    {
        foreach ($suffixes as $suffix => $replacement) {
            if (!str_ends_with($word, (string) $suffix)) {
                continue;
            }

            $stem = substr($word, 0, -strlen((string) $suffix));

            return $this->measure($stem) > $minMeasure ? $stem . $replacement : $word;
        }

        return $word;
    }

    private function measure(string $stem): int
    {
        $length = strlen($stem);
        $index = 0;
        $measure = 0;

        while ($index < $length && $this->isConsonant($stem, $index)) {
            $index++;
        }

        while ($index < $length) {
            while ($index < $length && !$this->isConsonant($stem, $index)) {
                $index++;
            }

            if ($index >= $length) {
                break;
            }

            $measure++;

            while ($index < $length && $this->isConsonant($stem, $index)) {
                $index++;
            }
        }

        return $measure;
    }

    private function containsVowel(string $stem): bool
    {
        for ($index = 0, $length = strlen($stem); $index < $length; $index++) {
            if (!$this->isConsonant($stem, $index)) {
                return true;
            }
        }

        return false;
    }

    private function endsWithDoubleConsonant(string $stem): bool
    {
        $length = strlen($stem);

        if ($length < 2) {
            return false;
        }

        return $stem[$length - 1] === $stem[$length - 2] && $this->isConsonant($stem, $length - 1);
    }

    private function endsWithCvc(string $stem): bool
    {
        $length = strlen($stem);

        if ($length < 3) {
            return false;
        }

        if (!$this->isConsonant($stem, $length - 3)
            || $this->isConsonant($stem, $length - 2)
            || !$this->isConsonant($stem, $length - 1)) {
            return false;
        }

        return !in_array($stem[$length - 1], ['w', 'x', 'y'], true);
    }

    private function isConsonant(string $stem, int $index): bool
    {
        $character = $stem[$index];

        if (isset(self::VOWELS[$character])) {
            return false;
        }

        if ($character !== 'y') {
            return true;
        }

        return $index === 0 || !$this->isConsonant($stem, $index - 1);
    }

    private function byLengthDescending(array $suffixes): array
    {
        uksort($suffixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $suffixes;
    }
}
