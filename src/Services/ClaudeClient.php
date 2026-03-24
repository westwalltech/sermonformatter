<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use NewSong\SermonFormatter\Support\Logger;

class ClaudeClient
{
    protected string $apiKey;

    protected string $model;

    protected int $maxTokens;

    protected string $apiVersion;

    public function __construct()
    {
        $this->apiKey = config('sermon-formatter.anthropic.api_key', '');
        $this->model = config('sermon-formatter.anthropic.model', 'claude-sonnet-4-20250514');
        $this->maxTokens = config('sermon-formatter.anthropic.max_tokens', 8192);
        $this->apiVersion = config('sermon-formatter.anthropic.api_version', '2023-06-01');
    }

    public function send(string $systemPrompt, string $userMessage): ClaudeResponse
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        Logger::debug('Sending request to Claude API', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system_length' => strlen($systemPrompt),
            'user_length' => strlen($userMessage),
        ]);

        $response = Http::timeout(120)
            ->retry(2, 5000, function ($exception) {
                // Only retry on server errors or rate limits
                if ($exception instanceof RequestException) {
                    $status = $exception->response?->status();

                    return $status === 429 || $status >= 500;
                }

                return false;
            })
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown API error');
            $status = $response->status();
            Logger::error('Claude API request failed', [
                'status' => $status,
                'error' => $error,
            ]);

            throw new \RuntimeException("Claude API error ({$status}): {$error}");
        }

        $claudeResponse = ClaudeResponse::fromApiResponse($response->json());

        Logger::info('Claude API response received', [
            'model' => $claudeResponse->model,
            'input_tokens' => $claudeResponse->inputTokens,
            'output_tokens' => $claudeResponse->outputTokens,
            'stop_reason' => $claudeResponse->stopReason,
        ]);

        return $claudeResponse;
    }
}
