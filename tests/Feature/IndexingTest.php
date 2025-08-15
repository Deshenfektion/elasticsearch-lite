<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Index\Codec\VarIntCodec;
use EsLite\Index\IndexingStatus;
use EsLite\Tests\Support\EngineTestCase;

final class IndexingTest extends EngineTestCase
{
    public function testIndexingStoresDocumentAndPostings(): void
    {
        $result = $this->index($this->document('doc-1', 'Inverted index', 'An inverted index maps terms to documents.'));

        self::assertSame(IndexingStatus::Created, $result->status);
        self::assertGreaterThan(0, $result->tokenCount);
        self::assertSame(1, $this->app->documents()->count());
        self::assertGreaterThan(0, $this->app->postings()->count());
    }

    public function testDocumentFrequencyCountsDocumentsNotOccurrences(): void
    {
        $this->index($this->document('doc-1', 'Index', 'index index index'));
        $this->index($this->document('doc-2', 'Other', 'index once'));

        $term = $this->app->terms()->findMany(['index'])['index'];

        self::assertSame(2, $term->documentFrequency);
        self::assertSame(5, $term->totalFrequency);
    }

    public function testUnchangedDocumentsAreSkipped(): void
    {
        $payload = $this->document('doc-1', 'Stable', 'The body never changes.');
        $this->index($payload);
        $postings = $this->app->postings()->count();

        $second = $this->index($payload);

        self::assertSame(IndexingStatus::Unchanged, $second->status);
        self::assertSame($postings, $this->app->postings()->count());
    }

    public function testUpdatingADocumentReplacesItsPostings(): void
    {
        $this->index($this->document('doc-1', 'First', 'alpha alpha beta'));
        $updated = $this->index($this->document('doc-1', 'Second', 'gamma'));

        self::assertSame(IndexingStatus::Updated, $updated->status);

        $terms = $this->app->terms()->findMany(['alpha', 'beta', 'gamma']);

        self::assertSame(0, $terms['alpha']->documentFrequency);
        self::assertSame(0, $terms['beta']->documentFrequency);
        self::assertSame(1, $terms['gamma']->documentFrequency);
        self::assertSame(1, $this->app->documents()->count());
    }

    public function testUpdatingKeepsFrequenciesConsistentForSurvivingTerms(): void
    {
        $this->index($this->document('doc-1', 'First', 'alpha beta'));
        $this->index($this->document('doc-2', 'Second', 'alpha gamma'));
        $this->index($this->document('doc-1', 'First revised', 'alpha delta'));

        $terms = $this->app->terms()->findMany(['alpha', 'beta', 'delta']);

        self::assertSame(2, $terms['alpha']->documentFrequency);
        self::assertSame(0, $terms['beta']->documentFrequency);
        self::assertSame(1, $terms['delta']->documentFrequency);
    }

    public function testDeletingADocumentRemovesPostingsAndDecrementsFrequencies(): void
    {
        $this->index($this->document('doc-1', 'First', 'alpha beta'));
        $this->index($this->document('doc-2', 'Second', 'alpha'));

        self::assertTrue($this->app->indexingService()->delete('doc-1'));

        $terms = $this->app->terms()->findMany(['alpha', 'beta']);

        self::assertSame(1, $terms['alpha']->documentFrequency);
        self::assertSame(0, $terms['beta']->documentFrequency);
        self::assertSame(1, $this->app->documents()->count());
        self::assertSame(1, $this->app->indexReader()->collectionStatistics()->documentCount);
    }

    public function testDeletingAnUnknownDocumentReportsFailure(): void
    {
        self::assertFalse($this->app->indexingService()->delete('missing'));
    }

    public function testCollectionStatisticsTrackFieldLengths(): void
    {
        $this->index($this->document('doc-1', 'Short title', 'one two three four five'));
        $statistics = $this->app->indexReader()->collectionStatistics();
        $bodyId = $this->app->fields()->id('body');

        self::assertSame(1, $statistics->documentCount);
        self::assertSame(5.0, $statistics->averageFieldLength($bodyId));
    }

    public function testPositionsAreStoredAsDeltaEncodedBlobs(): void
    {
        $this->index($this->document('doc-1', 'Positions', 'alpha beta alpha gamma alpha'));

        $termId = $this->app->terms()->findMany(['alpha'])['alpha']->id;
        $row = $this->connection->selectOne(
            'SELECT positions FROM postings WHERE term_id = ? AND field_id = ?',
            [$termId, $this->app->fields()->id('body')],
        );

        self::assertNotNull($row);
        self::assertSame([0, 2, 4], VarIntCodec::decodeSorted((string) $row['positions']));
    }

    public function testTagsAndCategoriesAreNormalisedIntoTheirOwnTables(): void
    {
        $this->index($this->document('doc-1', 'Tagged', 'body text', [
            'category' => 'Guides',
            'tags' => ['index', 'basics'],
        ]));
        $this->index($this->document('doc-2', 'Also tagged', 'body text', [
            'category' => 'Guides',
            'tags' => ['index'],
        ]));

        self::assertSame(['Guides' => 2], $this->app->categories()->counts());
        self::assertSame(['index' => 2, 'basics' => 1], $this->app->tags()->counts());
    }

    public function testTagsAreSearchableThroughTheTagsField(): void
    {
        $this->index($this->document('doc-1', 'Tagged', 'unrelated body', ['tags' => ['skiplist']]));

        self::assertSame(1, $this->search('tags:skiplist')->total);
    }

    public function testClearRemovesEverything(): void
    {
        $this->seedCorpus();
        $this->app->indexWriter()->clear();

        self::assertSame(0, $this->app->documents()->count());
        self::assertSame(0, $this->app->postings()->count());
        self::assertSame(0, $this->app->indexReader()->collectionStatistics()->documentCount);
    }

    public function testHtmlAndJsonDocumentsShareTheSameIndex(): void
    {
        $this->index([
            'id' => 'html-1',
            'media_type' => 'text/html',
            'content' => '<html><head><title>HTML doc</title></head><body><p>shared vocabulary</p></body></html>',
        ]);
        $this->index([
            'id' => 'json-1',
            'media_type' => 'application/json',
            'content' => (string) json_encode(['title' => 'JSON doc', 'body' => 'shared vocabulary']),
        ]);

        self::assertSame(2, $this->search('shared vocabulary')->total);
    }
}
