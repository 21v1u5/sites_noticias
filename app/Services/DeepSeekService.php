<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Thin wrapper around the DeepSeek chat completions API (OpenAI-compatible).
 * Timeout and retry are generous by design: article generation routinely
 * takes 10-30s, and a transient 5xx should not sink the whole queued job.
 */
class DeepSeekService
{
    public function __construct(private readonly HttpFactory $http) {}

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $response = $this->http
            ->baseUrl(rtrim(config('services.deepseek.base_url'), '/'))
            ->withToken(config('services.deepseek.api_key'))
            ->timeout((int) config('services.deepseek.timeout'))
            ->retry(
                (int) config('services.deepseek.retry_times'),
                (int) config('services.deepseek.retry_delay_ms'),
                fn (\Throwable $e) => $e instanceof RequestException && $e->response->serverError(),
            )
            ->acceptJson()
            ->post('/chat/completions', [
                'model' => config('services.deepseek.model'),
                'temperature' => 0.7,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        $response->throw();

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('DeepSeek retornou uma resposta vazia.');
        }

        return $content;
    }
}
