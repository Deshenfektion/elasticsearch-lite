<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Search\SearchRequest;
use EsLite\Service\SearchService;
use EsLite\Support\Config;

final class SearchController
{
    public function __construct(
        private readonly SearchService $search,
        private readonly Config $config,
    ) {
    }

    public function search(Request $request): Response
    {
        $searchRequest = SearchRequest::fromArray($request->parameters(), $this->config);

        return Response::json($this->search->search($searchRequest)->toArray());
    }
}
