<?php

declare(strict_types=1);

namespace EsLite\Ranking;

final readonly class Explanation
{
    public array $details;

    public function __construct(
        public string $description,
        public float $value,
        array $details = [],
    ) {
        $this->details = array_values($details);
    }

    public static function of(string $description, float $value, Explanation ...$details): self
    {
        return new self($description, $value, $details);
    }

    public function withValue(float $value): self
    {
        return new self($this->description, $value, $this->details);
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'value' => round($this->value, 6),
            'details' => array_map(static fn (self $detail): array => $detail->toArray(), $this->details),
        ];
    }
}
