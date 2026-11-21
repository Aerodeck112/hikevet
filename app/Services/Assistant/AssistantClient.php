<?php

namespace App\Services\Assistant;

/** Legatura cu modelul de limbaj; in teste se inlocuieste cu un raspuns fix. */
interface AssistantClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function reply(string $instructions, string $knowledge, array $messages): AssistantReply;
}
