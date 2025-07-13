<?php

declare(strict_types=1);

namespace EsLite\Analysis;

use EsLite\Analysis\Filter\ApostropheFilter;
use EsLite\Analysis\Filter\AsciiFoldingFilter;
use EsLite\Analysis\Filter\LengthFilter;
use EsLite\Analysis\Filter\LowercaseFilter;
use EsLite\Analysis\Filter\StemFilter;
use EsLite\Analysis\Filter\StopWordFilter;
use EsLite\Analysis\Stemmer\CachingStemmer;
use EsLite\Analysis\Stemmer\NoopStemmer;
use EsLite\Analysis\Stemmer\PorterStemmer;
use EsLite\Analysis\Stemmer\Stemmer;
use EsLite\Exception\ConfigurationException;
use EsLite\Support\Config;

final class AnalyzerFactory
{
    public static function fromConfig(Config $config): Analyzer
    {
        $filters = [
            new ApostropheFilter(),
            new LowercaseFilter(),
        ];

        if ($config->bool('app.analysis.ascii_folding', true)) {
            $filters[] = new AsciiFoldingFilter();
        }

        $filters[] = new LengthFilter(
            $config->int('app.analysis.min_token_length', 2),
            $config->int('app.analysis.max_token_length', 40),
        );

        $stopWords = StopWords::named($config->string('app.analysis.stopwords', 'english'));

        if ($stopWords->count() > 0) {
            $filters[] = new StopWordFilter($stopWords);
        }

        $stemmer = self::stemmer($config->string('app.analysis.stemmer', 'porter'));

        if (!$stemmer instanceof NoopStemmer) {
            $filters[] = new StemFilter($stemmer);
        }

        return new Analyzer(new StandardTokenizer(), ...$filters);
    }

    public static function standard(): Analyzer
    {
        return new Analyzer(
            new StandardTokenizer(),
            new ApostropheFilter(),
            new LowercaseFilter(),
            new AsciiFoldingFilter(),
            new LengthFilter(),
            new StopWordFilter(StopWords::english()),
            new StemFilter(new CachingStemmer(new PorterStemmer())),
        );
    }

    public static function minimal(): Analyzer
    {
        return new Analyzer(new StandardTokenizer(), new LowercaseFilter());
    }

    private static function stemmer(string $name): Stemmer
    {
        return match (strtolower($name)) {
            'porter' => new CachingStemmer(new PorterStemmer()),
            'none', '' => new NoopStemmer(),
            default => throw ConfigurationException::unknown('stemmer', $name, ['porter', 'none']),
        };
    }
}
