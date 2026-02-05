<?php

namespace NewSong\SermonFormatter\Console\Commands;

use Illuminate\Console\Command;
use NewSong\SermonFormatter\Services\ClaudeClient;
use NewSong\SermonFormatter\Support\Logger;

class TestClaudeCommand extends Command
{
    protected $signature = 'sermon-formatter:test-claude';

    protected $description = 'Test Claude API connectivity and configuration';

    public function handle(ClaudeClient $client): int
    {
        $this->info('Testing Claude API connection...');
        $this->newLine();

        $apiKey = config('sermon-formatter.anthropic.api_key');
        if (empty($apiKey)) {
            $this->error('ANTHROPIC_API_KEY is not set in your .env file.');

            return self::FAILURE;
        }

        $this->info('API Key: '.substr($apiKey, 0, 12).'...');
        $this->info('Model: '.config('sermon-formatter.anthropic.model'));
        $this->info('Max Tokens: '.config('sermon-formatter.anthropic.max_tokens'));
        $this->newLine();

        try {
            $response = $client->send(
                'You are a test assistant.',
                'Reply with exactly: "Claude API connection successful." Nothing else.'
            );

            $this->info('Response: '.$response->content);
            $this->info('Model used: '.$response->model);
            $this->info('Input tokens: '.$response->inputTokens);
            $this->info('Output tokens: '.$response->outputTokens);
            $this->info('Stop reason: '.$response->stopReason);
            $this->newLine();
            $this->info('Claude API test passed!');

            Logger::info('Claude API test successful', [
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Claude API test failed: '.$e->getMessage());
            Logger::error('Claude API test failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
