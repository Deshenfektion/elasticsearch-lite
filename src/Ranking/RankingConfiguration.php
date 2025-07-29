<?php

declare(strict_types=1);

namespace EsLite\Ranking;

use EsLite\Support\Config;

final readonly class RankingConfiguration
{
    public function __construct(
        public ScoringModel $model,
        public float $phraseBoost = 2.0,
        public bool $coordination = true,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            new TfIdfModel($config->bool('app.ranking.tfidf.length_normalisation', true)),
            $config->float('app.ranking.phrase_boost', 2.0),
            $config->bool('app.ranking.coordination', true),
        );
    }

    public static function default(): self
    {
        return new self(new TfIdfModel());
    }

    public function withModel(ScoringModel $model): self
    {
        return new self($model, $this->phraseBoost, $this->coordination);
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model->name(),
            'parameters' => $this->model->parameters(),
            'phrase_boost' => $this->phraseBoost,
            'coordination' => $this->coordination,
        ];
    }
}
