<?php

namespace App\Services\Assistant;

final class AssistantReply
{
    public function __construct(
        public readonly string $text,
        /** True when the model declined to answer for safety reasons. */
        public readonly bool $refused = false,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}
}
