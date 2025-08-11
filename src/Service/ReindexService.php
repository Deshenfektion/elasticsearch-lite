<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Index\IndexWriter;
use EsLite\Repository\DocumentRepository;
use EsLite\Support\Stopwatch;

final class ReindexService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly IndexWriter $writer,
        private readonly int $batchSize = 250,
    ) {
    }

    public function reindex(?callable $onProgress = null): array
    {
        $stopwatch = new Stopwatch();
        $this->writer->clearIndex();

        $documents = 0;
        $tokens = 0;

        $this->documents->chunkIds($this->batchSize, function (array $ids) use (&$documents, &$tokens, $onProgress): void {
            foreach ($this->documents->findMany($ids) as $stored) {
                $result = $this->writer->write($stored->toParsed(), true);
                $documents++;
                $tokens += $result->tokenCount;
            }

            if ($onProgress !== null) {
                $onProgress($documents);
            }
        });

        return [
            'documents' => $documents,
            'tokens' => $tokens,
            'took_ms' => $stopwatch->elapsedMillis(),
        ];
    }
}
