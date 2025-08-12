<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Document\SourceDocument;
use EsLite\Http\Exception\BadRequestException;
use EsLite\Http\Exception\NotFoundException;
use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Index\IndexingResult;
use EsLite\Service\IndexingService;

final class DocumentController
{
    private const int MAX_BULK = 500;

    public function __construct(private readonly IndexingService $indexing)
    {
    }

    public function store(Request $request): Response
    {
        $payload = $request->body;

        if ($payload === []) {
            throw BadRequestException::invalid('Request body must contain a document or a "documents" array.');
        }

        if (isset($payload['documents'])) {
            return $this->storeMany($payload['documents']);
        }

        $result = $this->indexing->ingest(SourceDocument::fromArray($payload));

        return Response::json($result->toArray(), $result->status->value === 'created' ? 201 : 200);
    }

    public function show(Request $request, array $parameters): Response
    {
        $document = $this->indexing->find($parameters['id'] ?? '');

        if ($document === null) {
            throw NotFoundException::document((string) ($parameters['id'] ?? ''));
        }

        return Response::json($document->toArray());
    }

    public function destroy(Request $request, array $parameters): Response
    {
        $externalId = (string) ($parameters['id'] ?? '');

        if (!$this->indexing->delete($externalId)) {
            throw NotFoundException::document($externalId);
        }

        return Response::json(['id' => $externalId, 'status' => 'deleted']);
    }

    private function storeMany(mixed $documents): Response
    {
        if (!is_array($documents) || $documents === []) {
            throw BadRequestException::invalid('"documents" must be a non-empty array.');
        }

        if (count($documents) > self::MAX_BULK) {
            throw BadRequestException::invalid(
                sprintf('At most %d documents can be indexed per request.', self::MAX_BULK),
                ['limit' => self::MAX_BULK],
            );
        }

        $sources = [];

        foreach ($documents as $document) {
            if (!is_array($document)) {
                throw BadRequestException::invalid('Every entry in "documents" must be an object.');
            }

            $sources[] = SourceDocument::fromArray($document);
        }

        $results = $this->indexing->ingestMany($sources);
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($results as $result) {
            $counts[$result->status->value] = ($counts[$result->status->value] ?? 0) + 1;
        }

        return Response::json([
            'indexed' => count($results),
            'counts' => $counts,
            'results' => array_map(static fn (IndexingResult $result): array => $result->toArray(), $results),
        ], 201);
    }
}
