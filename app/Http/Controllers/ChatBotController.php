<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatBotController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        try {
            return response()->json($this->aiService->generateResponse($validated['message']));
        } catch (Throwable $e) {
            $payload = [
                'response' => 'Backend Crash: '.$e->getMessage(),
            ];

            if (config('app.debug')) {
                $payload['debug_info'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        }
    }

    public function history(): JsonResponse
    {
        return response()->json([
            'messages' => $this->aiService->savedMessages(),
        ]);
    }
}
