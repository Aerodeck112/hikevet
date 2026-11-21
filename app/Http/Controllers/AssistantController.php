<?php

namespace App\Http\Controllers;

use App\Services\Assistant\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssistantController extends Controller
{
    public function ask(Request $request, Assistant $assistant): JsonResponse
    {
        abort_unless(Assistant::enabled(), 404);
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
            'conversation' => ['required', 'string', 'alpha_dash', 'min:8', 'max:40'],
        ]);

        try {
            return response()->json($assistant->ask($data['messages'], $data['conversation'], (string) $request->ip()));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
