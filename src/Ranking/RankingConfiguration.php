<?php

declare(strict_types=1);

namespace EsLite\Ranking;

use EsLite\Exception\ConfigurationException;
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
            self::model($config),
            $config->float('app.ranking.phrase_boost', 2.0),
            $config->bool('app.ranking.coordination', true),
        );
    }

    public static function default(): self
    {
        return new self(new Bm25Model());
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

    private static function model(Config $config): ScoringModel
    {
        $name = strtolower($config->string('app.ranking.model', 'bm25'));

        return match ($name) {
            'bm25' => new Bm25Model(
                $config->float('app.ranking.bm25.k1', 1.2),
                $config->float('app.ranking.bm25.b', 0.75),
            ),
            'tfidf' => new TfIdfModel($config->bool('app.ranking.tfidf.length_normalisation', true)),
            default => throw ConfigurationException::unknown('ranking model', $name, ['bm25', 'tfidf']),
        };
    }
}
