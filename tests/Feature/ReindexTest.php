<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Tests\Support\EngineTestCase;

final class ReindexTest extends EngineTestCase
{
    public function testReindexingReproducesTheSameIndex(): void
    {
        $this->seedCorpus();

        $before = [
            'terms' => $this->app->terms()->count(),
            'postings' => $this->app->postings()->count(),
            'documents' => $this->app->documents()->count(),
        ];
        $ranking = $this->titles($this->search('inverted index'));

        $this->app->indexReader()->refresh();
        $statistics = $this->app->reindexService()->reindex();
        $this->app->indexReader()->refresh();

        self::assertSame(4, $statistics['documents']);
        self::assertSame($before['documents'], $this->app->documents()->count());
        self::assertSame($before['postings'], $this->app->postings()->count());
        self::assertSame($before['terms'], $this->app->terms()->count());

        $after = $this->search('inverted index');

        self::assertSame($ranking, $this->titles($after));
        self::assertGreaterThan(0.0, $after->hits[0]->score);
    }

    public function testReindexingRecomputesCollectionStatistics(): void
    {
        $this->seedCorpus();
        $expected = $this->app->indexReader()->collectionStatistics()->toArray();

        $this->app->reindexService()->reindex();
        $this->app->indexReader()->refresh();

        self::assertSame($expected, $this->app->indexReader()->collectionStatistics()->toArray());
    }

    public function testReindexingRepairsAnIndexThatWasEmptied(): void
    {
        $this->seedCorpus();
        $this->app->indexWriter()->clearIndex();
        $this->app->indexReader()->refresh();

        self::assertSame(0, $this->search('inverted index')->total);

        $this->app->reindexService()->reindex();
        $this->app->indexReader()->refresh();

        self::assertGreaterThan(0, $this->search('inverted index')->total);
    }

    public function testReindexingReportsProgress(): void
    {
        $this->seedCorpus();
        $progress = [];

        $this->app->reindexService()->reindex(static function (int $done) use (&$progress): void {
            $progress[] = $done;
        });

        self::assertNotSame([], $progress);
        self::assertSame(4, end($progress));
    }

    public function testReindexingAnEmptyCollectionIsSafe(): void
    {
        $statistics = $this->app->reindexService()->reindex();

        self::assertSame(0, $statistics['documents']);
        self::assertSame(0, $statistics['tokens']);
    }
}
