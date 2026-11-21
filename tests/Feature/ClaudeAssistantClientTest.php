<?php

namespace Tests\Feature;

use App\Services\Assistant\ClaudeAssistantClient;
use App\Support\Settings;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Clientul real (SDK Anthropic) pe o conexiune simulata: ce trimite si cum citeste raspunsul. */
class ClaudeAssistantClientTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    private function claude(array $responses): ClaudeAssistantClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ClaudeAssistantClient(new Guzzle(['handler' => $stack, 'http_errors' => false]));
    }

    private function message(array $override = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(array_merge([
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'x'], ['type' => 'text', 'text' => 'Bună! Abonamentul costă 99 lei pe lună.']],
            'stop_reason' => 'end_turn', 'stop_sequence' => null, 'stop_details' => null,
            'usage' => ['input_tokens' => 50, 'output_tokens' => 20, 'cache_read_input_tokens' => 3000, 'cache_creation_input_tokens' => 0],
        ], $override)));
    }

    public function test_request_uses_cached_knowledge_effort_and_server_side_fallback(): void
    {
        Settings::set('assistant.api_key', 'sk-ant-test', true);
        $reply = $this->claude([$this->message()])->reply('Instrucțiuni', 'Cunoștințe clinică', [['role' => 'user', 'content' => 'Cât costă?']]);

        $this->assertSame('answered', $reply->status);
        $this->assertSame('Bună! Abonamentul costă 99 lei pe lună.', $reply->text);
        $this->assertSame(3000, $reply->cacheReadTokens);

        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertStringEndsWith('/v1/messages', (string) $request->getUri());
        $this->assertStringContainsString('sk-ant-test', $request->getHeaderLine('Authorization').$request->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $body['system'][1]['cache_control']);
        $this->assertSame('Cunoștințe clinică', $body['system'][1]['text']);
        $this->assertArrayNotHasKey('thinking', $body);
    }

    public function test_refusals_and_api_errors_become_statuses_not_exceptions(): void
    {
        Settings::set('assistant.api_key', 'sk-ant-test', true);
        $refusal = $this->message(['content' => [], 'stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => null, 'explanation' => null]]);
        $this->assertSame('refused', $this->claude([$refusal])->reply('i', 'k', [['role' => 'user', 'content' => 'x']])->status);

        $bad = new Response(401, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]));
        $this->assertSame('error', $this->claude([$bad])->reply('i', 'k', [['role' => 'user', 'content' => 'x']])->status);
    }
}
