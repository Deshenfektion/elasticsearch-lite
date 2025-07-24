<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Document\SourceDocument;
use EsLite\Document\StoredDocument;
use EsLite\Index\IndexingResult;
use EsLite\Index\IndexWriter;
use EsLite\Parser\ParserRegistry;
use EsLite\Repository\DocumentRepository;

final class IndexingService
{
    public function __construct(
        private readonly ParserRegistry $parsers,
        private readonly IndexWriter $writer,
        private readonly DocumentRepository $documents,
    ) {
    }

    public function ingest(SourceDocument $source): IndexingResult
    {
        return $this->writer->write($this->parsers->parse($source));
    }

    public function ingestMany(array $sources): array
    {
        $results = [];

        foreach ($sources as $source) {
            $results[] = $this->ingest($source);
        }

        return $results;
    }

    public function delete(string $externalId): bool
    {
        return $this->writer->delete($externalId);
    }

    public function find(string $externalId): ?StoredDocument
    {
        return $this->documents->findByExternalId($externalId);
    }

    public function supportedMediaTypes(): array
    {
        return $this->parsers->mediaTypes();
    }
}
