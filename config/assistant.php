<?php

// The chat assistant in the corner of every page. With an Anthropic API key it answers with Claude,
// told about the site and the cars for sale; without one it answers common questions itself.
return [
    'key' => env('ANTHROPIC_API_KEY', ''),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
    'max_tokens' => 400,
    // how many turns of the conversation are sent back each time
    'history' => 12,
    'max_message' => 1000,
];
