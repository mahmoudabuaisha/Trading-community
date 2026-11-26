<?php

/*
| The AI assistant, a paid service opened per member in their subscription.
| It stays switched off until an API key is set in the server's .env.
*/
return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    // The model answering members; a cheaper one (e.g. claude-haiku-5-5) costs less per message.
    'model' => env('ASSISTANT_MODEL', 'claude-opus-5-5'),
    // Messages a member may send per day; protects the monthly bill.
    'daily_limit' => (int) env('ASSISTANT_DAILY_LIMIT', 20),
];
