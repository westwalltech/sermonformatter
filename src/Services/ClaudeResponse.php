<?php

namespace NewSong\SermonFormatter\Services;

class ClaudeResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $model,
        public readonly string $stopReason,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        $content = '';
        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        return new self(
            content: $content,
            inputTokens: $response['usage']['input_tokens'] ?? 0,
            outputTokens: $response['usage']['output_tokens'] ?? 0,
            model: $response['model'] ?? 'unknown',
            stopReason: $response['stop_reason'] ?? 'unknown',
        );
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
