<?php

namespace App\Http\Controllers;

use App\Services\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// The chat assistant: the browser sends the conversation so far and gets the next answer
class AssistantController extends Controller
{
    public function __invoke(Request $request, Assistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:'.(int) config('assistant.max_message')],
        ]);

        // a local model may take a while; leave room for it and for the fallback answer
        set_time_limit((int) config('assistant.ollama.timeout') + 20);

        return response()->json($assistant->reply($data['messages']));
    }
}
