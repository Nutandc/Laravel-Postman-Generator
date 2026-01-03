<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\ValueObjects;

final class EndpointMetadata
{
    /**
     * @param string[] $tags
     * @param Header[] $headers
     * @param Parameter[] $queryParams
     * @param Parameter[] $bodyParams
     * @param ResponseDefinition[] $responses
     */
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly ?string $auth = null,
        public readonly array $headers = [],
        public readonly array $queryParams = [],
        public readonly array $bodyParams = [],
        public readonly array $responses = [],
        public readonly ?bool $deprecated = null,
    ) {
    }

    public function merge(self $override): self
    {
        return new self(
            summary: $override->summary ?? $this->summary,
            description: $override->description ?? $this->description,
            tags: $this->mergeTags($this->tags, $override->tags),
            auth: $override->auth ?? $this->auth,
            headers: $this->mergeHeaders($this->headers, $override->headers),
            queryParams: $this->mergeParams($this->queryParams, $override->queryParams),
            bodyParams: $this->mergeParams($this->bodyParams, $override->bodyParams),
            responses: $this->mergeResponses($this->responses, $override->responses),
            deprecated: $override->deprecated ?? $this->deprecated,
        );
    }

    /**
     * @param string[] $base
     * @param string[] $override
     * @return string[]
     */
    private function mergeTags(array $base, array $override): array
    {
        $tags = array_values(array_unique(array_merge($base, $override)));

        return array_values(array_filter($tags, fn (string $tag): bool => $tag !== ''));
    }

    /**
     * @param Header[] $base
     * @param Header[] $override
     * @return Header[]
     */
    private function mergeHeaders(array $base, array $override): array
    {
        $merged = [];
        foreach (array_merge($base, $override) as $header) {
            $merged[strtolower($header->name)] = $header;
        }

        return array_values($merged);
    }

    /**
     * @param Parameter[] $base
     * @param Parameter[] $override
     * @return Parameter[]
     */
    private function mergeParams(array $base, array $override): array
    {
        $merged = [];
        foreach (array_merge($base, $override) as $param) {
            $merged[$param->name] = $param;
        }

        return array_values($merged);
    }

    /**
     * @param ResponseDefinition[] $base
     * @param ResponseDefinition[] $override
     * @return ResponseDefinition[]
     */
    private function mergeResponses(array $base, array $override): array
    {
        $merged = [];
        foreach (array_merge($base, $override) as $response) {
            $merged[$response->status] = $response;
        }

        return array_values($merged);
    }
}
