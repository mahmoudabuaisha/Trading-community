<?php

namespace App\Services\Translation;

use Anthropic\Client;

/** Translates through Claude, asking for JSON that matches a fixed schema. */
final class ClaudeTextTranslator implements TextTranslator
{
    private const LANGUAGES = ['ar' => 'Arabic', 'en' => 'English', 'fr' => 'French'];

    public function __construct(private readonly Client $client, private readonly string $model) {}

    public function translate(array $texts, array $locales): array
    {
        $fields = array_keys($texts);
        $fieldSchema = [
            'type' => 'object',
            'properties' => array_fill_keys($fields, ['type' => 'string']),
            'required' => $fields,
            'additionalProperties' => false,
        ];
        $schema = [
            'type' => 'object',
            'properties' => array_fill_keys($locales, $fieldSchema),
            'required' => $locales,
            'additionalProperties' => false,
        ];
        $targets = implode(', ', array_map(fn (string $locale): string => $locale.' ('.(self::LANGUAGES[$locale] ?? $locale).')', $locales));

        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: 8000,
            system: 'You translate the interface content of SN Tasks, a productivity and self-development app. '
                .'Translate every field into each target language. Keep the meaning, the motivating tone, emojis, line breaks, '
                .'numbers, links and {placeholders}. If a text is already in a target language, return it unchanged for that language. '
                .'Use Modern Standard Arabic for Arabic.',
            messages: [[
                'role' => 'user',
                'content' => "Target languages: {$targets}.\nTexts (JSON, field => text):\n".json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        );

        $json = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json .= $block->text;
            }
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('The translator returned no usable JSON.');
        }

        $result = [];
        foreach ($locales as $locale) {
            foreach ($fields as $field) {
                $text = $data[$locale][$field] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $result[$locale][$field] = trim($text);
                }
            }
        }

        return $result;
    }
}
