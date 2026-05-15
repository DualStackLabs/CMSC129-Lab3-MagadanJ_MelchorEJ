<?php

namespace App\Services;

use App\Models\AIChatMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AIService
{
    public function __construct(
        private readonly PromptService $promptService,
        private readonly FunctionCallService $functionCallService,
    ) {
    }

    public function generateReply(string $message): string
    {
        return $this->generateResponse($message)['response'];
    }

    public function generateResponse(string $message): array
    {
        $pendingAction = $this->sessionValue('ai_pending_action');

        if ($pendingAction && $this->functionCallService->isConfirmation($message)) {
            $this->sessionForget('ai_pending_action');
            $result = $this->functionCallService->executeAction($pendingAction);
            $this->rememberExchange($message, $result['response']);

            return [
                'response' => $result['response'],
                'action_executed' => true,
                'refresh' => (bool) ($result['refresh'] ?? false),
                'entry_id' => $result['entry_id'] ?? null,
            ];
        }

        if ($pendingAction && $this->functionCallService->isCancellation($message)) {
            $this->sessionForget('ai_pending_action');
            $reply = 'Cancelled. I did not change your journal.';
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'action_cancelled' => true,
            ];
        }

        $action = $this->functionCallService->planAction($message, $this->sessionValue('ai_last_entry_id'));

        if (($action['type'] ?? null) === 'clarify') {
            $this->rememberExchange($message, $action['response']);

            return [
                'response' => $action['response'],
            ];
        }

        if ($action) {
            if ($action['requires_confirmation'] ?? false) {
                $reply = $this->functionCallService->confirmationText($action);
                $this->sessionPut('ai_pending_action', $action);
                $this->rememberExchange($message, $reply);

                return [
                    'response' => $reply,
                    'requires_confirmation' => true,
                ];
            }

            $result = $this->functionCallService->executeAction($action);
            $this->rememberExchange($message, $result['response']);

            return [
                'response' => $result['response'],
                'action_executed' => true,
                'refresh' => (bool) ($result['refresh'] ?? false),
                'entry_id' => $result['entry_id'] ?? null,
            ];
        }

        $reply = $this->askModel($message);
        $this->rememberExchange($message, $reply);

        return [
            'response' => $reply,
        ];
    }

    public function savedMessages(int $limit = 50): array
    {
        $sessionId = $this->sessionId();

        if (!$sessionId) {
            return [];
        }

        try {
            $messages = AIChatMessage::where('session_id', $sessionId)
                ->latest()
                ->take($limit)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (AIChatMessage $message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toISOString(),
                ])
                ->all();

            if ($messages !== []) {
                return $messages;
            }
        } catch (Throwable) {
            // Fall back to session history when the chat history table is not migrated yet.
        }

        return $this->sessionHistoryMessages($limit);
    }

    private function askModel(string $message): string
    {
        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is missing from .env');
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $facts = $this->functionCallService->journalFactsFor($message);
        $systemPrompt = $this->promptService->buildSystemPrompt($facts);

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
        ])
            ->acceptJson()
            ->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => $this->conversationContents($message),
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if ($response->failed()) {
            $error = data_get($response->json(), 'error.message', $response->body());
            throw new RuntimeException("Gemini API request failed ({$response->status()}): {$error}");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $this->extractText($payload);
    }

    private function conversationContents(string $message): array
    {
        $history = collect($this->contextHistory())
            ->take(-10)
            ->map(fn (array $item) => [
                'role' => $item['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $item['content']],
                ],
            ])
            ->values()
            ->all();

        $history[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $message],
            ],
        ];

        return $history;
    }

    private function rememberExchange(string $userMessage, string $assistantMessage): void
    {
        if (! request()->hasSession()) {
            return;
        }

        $history = collect($this->sessionValue('ai_chat_history', []))
            ->push(['role' => 'user', 'content' => $userMessage])
            ->push(['role' => 'assistant', 'content' => $assistantMessage])
            ->take(-10)
            ->values()
            ->all();

        $this->sessionPut('ai_chat_history', $history);
        $this->saveMessage('user', $userMessage);
        $this->saveMessage('assistant', $assistantMessage);
    }

    private function contextHistory(): array
    {
        $saved = $this->savedMessages(10);

        if ($saved !== []) {
            return $saved;
        }

        return $this->sessionValue('ai_chat_history', []);
    }

    private function sessionHistoryMessages(int $limit): array
    {
        return collect($this->sessionValue('ai_chat_history', []))
            ->take(-$limit)
            ->values()
            ->map(fn (array $message) => [
                'role' => $message['role'] ?? 'assistant',
                'content' => $message['content'] ?? '',
                'created_at' => null,
            ])
            ->all();
    }

    private function saveMessage(string $role, string $content, array $metadata = []): void
    {
        $sessionId = $this->sessionId();

        if (!$sessionId) {
            return;
        }

        try {
            AIChatMessage::create([
                'session_id' => $sessionId,
                'role' => $role,
                'content' => $content,
                'metadata' => $metadata ?: null,
            ]);
        } catch (Throwable) {
            // Keep the assistant usable even before the history migration has run.
        }
    }

    private function sessionValue(string $key, mixed $default = null): mixed
    {
        return request()->hasSession() ? session($key, $default) : $default;
    }

    private function sessionPut(string $key, mixed $value): void
    {
        if (request()->hasSession()) {
            session([$key => $value]);
        }
    }

    private function sessionForget(string $key): void
    {
        if (request()->hasSession()) {
            session()->forget($key);
        }
    }

    private function sessionId(): ?string
    {
        if (! request()->hasSession()) {
            return null;
        }

        return request()->session()->getId();
    }

    private function extractText(array $payload): string
    {
        $parts = data_get($payload, 'candidates.0.content.parts', []);
        $answer = collect($parts)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        if ($answer !== '') {
            return $answer;
        }

        $blockReason = data_get($payload, 'promptFeedback.blockReason');

        if ($blockReason) {
            throw new RuntimeException("Gemini returned no text. Block reason: {$blockReason}");
        }

        throw new RuntimeException('Gemini returned no text.');
    }
}
