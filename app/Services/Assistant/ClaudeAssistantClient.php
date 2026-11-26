<?php

namespace App\Services\Assistant;

use Anthropic\Client;

/** Calls Claude through the official Anthropic SDK. */
final class ClaudeAssistantClient implements AssistantClient
{
    public function __construct(private readonly Client $client, private readonly string $model) {}

    public function reply(array $system, array $messages): AssistantReply
    {
        $message = $this->client->beta->messages->create(
            model: $this->model,
            maxTokens: 1500,
            system: $system,
            messages: $messages,
            // A chat about the member's day: quick answers are worth more than deep deliberation.
            outputConfig: ['effort' => 'low'],
            // If the model declines for policy reasons, let the API retry on its default fallback.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AssistantReply(
            text: trim($text),
            refused: $message->stopReason === 'refusal',
            inputTokens: (int) ($message->usage->inputTokens ?? 0) + (int) ($message->usage->cacheReadInputTokens ?? 0),
            outputTokens: (int) ($message->usage->outputTokens ?? 0),
        );
    }
}
