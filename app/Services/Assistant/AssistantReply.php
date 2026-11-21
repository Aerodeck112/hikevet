<?php

namespace App\Services\Assistant;

/** Raspunsul primit de la model, cu consumul de tokeni pentru jurnal. */
final class AssistantReply
{
    public function __construct(
        public readonly string $text,
        public readonly string $status = 'answered',
        public readonly string $model = '',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
    ) {}
}
