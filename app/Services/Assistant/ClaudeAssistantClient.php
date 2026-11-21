<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

/**
 * Asistentul ruleaza pe Claude prin SDK-ul oficial Anthropic. Instructiunile si cunostintele stau
 * in promptul de sistem, marcate pentru cache (se platesc integral doar la prima intrebare).
 */
class ClaudeAssistantClient implements AssistantClient
{
    /** @param ClientInterface|null $transporter doar pentru teste (conexiune simulata) */
    public function __construct(private readonly ?ClientInterface $transporter = null) {}

    public function reply(string $instructions, string $knowledge, array $messages): AssistantReply
    {
        $key = (string) Settings::get('assistant.api_key');
        if ($key === '') {
            throw new RuntimeException('Cheia API pentru asistent nu este configurată.');
        }
        $model = (string) (Settings::get('assistant.model') ?: 'claude-opus-5-5');
        $options = ['timeout' => 60.0, 'maxRetries' => 1];
        if ($this->transporter) {
            $options['transporter'] = $this->transporter;
        }
        $client = new Client(apiKey: $key, requestOptions: $options, openTelemetry: false);

        try {
            $message = $client->beta->messages->create(
                maxTokens: 4000,
                messages: $messages,
                model: $model,
                // Daca modelul refuza o cerere din motive de siguranta, API-ul incearca automat modelul de rezerva.
                fallbacks: 'default',
                outputConfig: ['effort' => (string) (Settings::get('assistant.effort') ?: 'medium')],
                system: [
                    ['type' => 'text', 'text' => $instructions],
                    ['type' => 'text', 'text' => $knowledge, 'cacheControl' => ['type' => 'ephemeral', 'ttl' => '1h']],
                ],
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            Log::warning('Asistent AI: cheie API respinsă', ['message' => $e->getMessage()]);

            return new AssistantReply('', 'error', $model);
        } catch (RateLimitException $e) {
            return new AssistantReply('', 'busy', $model);
        } catch (APIStatusException|APIConnectionException $e) {
            Log::warning('Asistent AI: eroare API', ['message' => $e->getMessage()]);

            return new AssistantReply('', 'error', $model);
        }

        $usage = $message->usage;
        $tokens = [(int) ($usage->inputTokens ?? 0), (int) ($usage->outputTokens ?? 0), (int) ($usage->cacheReadInputTokens ?? 0)];

        if ($message->stopReason === 'refusal') {
            return new AssistantReply('', 'refused', $message->model, ...$tokens);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AssistantReply(trim($text), $text === '' ? 'empty' : 'answered', $message->model, ...$tokens);
    }
}
