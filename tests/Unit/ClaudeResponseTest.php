<?php

declare(strict_types=1);

use NewSong\SermonFormatter\Services\ClaudeResponse;

it('creates from API response', function () {
    $apiResponse = [
        'content' => [
            ['type' => 'text', 'text' => 'Hello, world!'],
        ],
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 50,
        ],
        'model' => 'claude-sonnet-4-20250514',
        'stop_reason' => 'end_turn',
    ];

    $response = ClaudeResponse::fromApiResponse($apiResponse);

    expect($response->content)->toBe('Hello, world!')
        ->and($response->inputTokens)->toBe(100)
        ->and($response->outputTokens)->toBe(50)
        ->and($response->model)->toBe('claude-sonnet-4-20250514')
        ->and($response->stopReason)->toBe('end_turn')
        ->and($response->totalTokens())->toBe(150);
});

it('handles empty content blocks', function () {
    $apiResponse = [
        'content' => [],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
        'model' => 'claude-sonnet-4-20250514',
        'stop_reason' => 'end_turn',
    ];

    $response = ClaudeResponse::fromApiResponse($apiResponse);

    expect($response->content)->toBe('');
});

it('concatenates multiple text blocks', function () {
    $apiResponse = [
        'content' => [
            ['type' => 'text', 'text' => 'Part 1 '],
            ['type' => 'text', 'text' => 'Part 2'],
        ],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        'model' => 'claude-sonnet-4-20250514',
        'stop_reason' => 'end_turn',
    ];

    $response = ClaudeResponse::fromApiResponse($apiResponse);

    expect($response->content)->toBe('Part 1 Part 2');
});
