<?php

namespace App\Http\Controllers;

use App\Services\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// The chat assistant: the browser sends the conversation so far and gets the next answer, streamed when it asks for it
class AssistantController extends Controller
{
    public function __invoke(Request $request, Assistant $assistant): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:'.(int) config('assistant.max_message')],
        ]);

        // a local model may take a while; leave room for it and for the fallback answer
        set_time_limit((int) config('assistant.ollama.timeout') + 20);

        if (! str_contains((string) $request->header('Accept'), 'application/x-ndjson')) {
            return response()->json($assistant->reply($data['messages']));
        }

        // the chat window: one JSON event per line, written out as the model writes the answer
        return response()->stream(function () use ($assistant, $data) {
            $assistant->stream($data['messages'], function (array $event) {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        }, 200, ['Content-Type' => 'application/x-ndjson', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }
}
