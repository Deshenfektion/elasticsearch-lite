<?php

declare(strict_types=1);

namespace EsLite\Highlight;

use EsLite\Support\Config;

final readonly class HighlightOptions
{
    public function __construct(
        public string $preTag = '<mark>',
        public string $postTag = '</mark>',
        public int $fragmentSize = 180,
        public int $maxFragments = 3,
        public string $ellipsis = '…',
        public array $fields = ['title', 'body'],
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->string('app.highlight.pre_tag', '<mark>'),
            $config->string('app.highlight.post_tag', '</mark>'),
            $config->int('app.highlight.fragment_size', 180),
            $config->int('app.highlight.max_fragments', 3),
        );
    }

    public function withTags(string $preTag, string $postTag): self
    {
        return new self($preTag, $postTag, $this->fragmentSize, $this->maxFragments, $this->ellipsis, $this->fields);
    }

    public function withFields(array $fields): self
    {
        return new self(
            $this->preTag,
            $this->postTag,
            $this->fragmentSize,
            $this->maxFragments,
            $this->ellipsis,
            $fields,
        );
    }
}
