<?php

// The chat assistant in the corner of every page.
//  - ollama: a local model (e.g. Qwen) on an Ollama server; it searches the cars itself through the same tools
//    the MCP server offers (App\Services\CarCatalog).
//  - anthropic: Claude, told about the site and the cars for sale.
//  - builtin: no model; answers the common questions and searches the cars by make, year and price.
// "auto" picks ollama when OLLAMA_URL is set, else anthropic when ANTHROPIC_API_KEY is set, else builtin.
// Whatever the driver, a model that fails or is too slow falls back to the built-in answers.
return [
    'driver' => env('ASSISTANT_DRIVER', 'auto'),

    'ollama' => [
        'url' => env('OLLAMA_URL', ''),
        'model' => env('OLLAMA_MODEL', 'qwen3:30b-a3b'),
        // seconds for the whole answer, tool calls included; after that the built-in answer is shown
        'timeout' => (int) env('OLLAMA_TIMEOUT', 45),
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
        'max_tool_rounds' => 3,
    ],

    'key' => env('ANTHROPIC_API_KEY', ''),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
    'max_tokens' => 400,

    // how many turns of the conversation are sent back each time
    'history' => 12,
    'max_message' => 1000,
];
