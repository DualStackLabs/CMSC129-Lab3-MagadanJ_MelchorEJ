<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIAssistantController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'mode' => 'nullable|in:query,crud',
        ]);

        try {
            return response()->json($this->aiService->generateResponse(
                $validated['message'],
                $validated['mode'] ?? ($request->routeIs('api.ai-assistant') ? 'crud' : 'query')
            ));
        } catch (Throwable $e) {
            Log::warning('AI API request failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $payload = [
                'response' => 'Sorry, the AI assistant could not respond right now. Please check your Gemini keys or try again later.',
            ];

            return response()->json($payload, 500);
        }
    }
}
