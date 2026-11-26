<?php

namespace App\Services\Assistant;

/** Something that turns a conversation into the assistant's next reply. Swapped for a fake in tests. */
interface AssistantClient
{
    /**
     * @param  list<array{type: string, text: string, cacheControl?: array<string, string>}>  $system
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function reply(array $system, array $messages): AssistantReply;
}
