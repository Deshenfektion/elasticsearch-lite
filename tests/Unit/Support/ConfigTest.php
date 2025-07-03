<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Support;

use EsLite\Exception\ConfigurationException;
use EsLite\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config([
            'app' => [
                'environment' => 'testing',
                'ranking' => ['model' => 'bm25', 'bm25' => ['k1' => 1.2, 'b' => 0.75]],
                'search' => ['default_size' => 10, 'log_queries' => false],
                'fields' => ['title' => ['id' => 1, 'boost' => 3.0]],
            ],
        ]);
    }

    public function testReadsNestedValuesWithDotPaths(): void
    {
        self::assertSame('bm25', $this->config->string('app.ranking.model'));
        self::assertSame(1.2, $this->config->float('app.ranking.bm25.k1'));
        self::assertSame(10, $this->config->int('app.search.default_size'));
        self::assertFalse($this->config->bool('app.search.log_queries'));
        self::assertSame(['title' => ['id' => 1, 'boost' => 3.0]], $this->config->array('app.fields'));
    }

    public function testFallsBackToDefaultsForMissingPaths(): void
    {
        self::assertSame('fallback', $this->config->string('app.missing', 'fallback'));
        self::assertSame(7, $this->config->int('app.missing.deeper', 7));
        self::assertFalse($this->config->has('app.missing'));
    }

    public function testThrowsWhenRequiredValueIsAbsent(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->config->string('app.ranking.absent');
    }

    public function testThrowsWhenTypesDoNotMatch(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->config->int('app.ranking.model');
    }

    public function testCoercesNumericStrings(): void
    {
        $config = new Config(['app' => ['size' => '25', 'boost' => '1.5']]);

        self::assertSame(25, $config->int('app.size'));
        self::assertSame(1.5, $config->float('app.boost'));
    }

    public function testWithReturnsAModifiedCopy(): void
    {
        $updated = $this->config->with('app.ranking.model', 'tfidf');

        self::assertSame('tfidf', $updated->string('app.ranking.model'));
        self::assertSame('bm25', $this->config->string('app.ranking.model'));
    }

    public function testWithCreatesMissingBranches(): void
    {
        $updated = $this->config->with('app.search.cache.shared', false);

        self::assertFalse($updated->bool('app.search.cache.shared'));
        self::assertSame(10, $updated->int('app.search.default_size'));
    }

    public function testMergeKeepsUntouchedKeys(): void
    {
        $merged = $this->config->merge(['app' => ['ranking' => ['model' => 'tfidf']]]);

        self::assertSame('tfidf', $merged->string('app.ranking.model'));
        self::assertSame(1.2, $merged->float('app.ranking.bm25.k1'));
    }

    public function testLoadsConfigurationFilesFromDisk(): void
    {
        $config = Config::load(dirname(__DIR__, 3) . '/config');

        self::assertTrue($config->has('app.fields.title.boost'));
        self::assertSame(3, count($config->array('app.fields')));
        self::assertContains($config->string('app.database.driver'), ['mysql', 'sqlite']);
    }
}
