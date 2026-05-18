<?php

namespace App\Services;

use App\Models\AIChatMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AIService
{
    private string $activeMode = 'query';

    public function __construct(
        private readonly PromptService $promptService,
        private readonly FunctionCallService $functionCallService,
    ) {
    }

    public function generateReply(string $message): string
    {
        return $this->generateResponse($message, 'query')['response'];
    }

    public function generateResponse(string $message, string $mode = 'query'): array
    {
        $this->activeMode = $this->normalizeMode($mode);

        return $this->activeMode === 'crud'
            ? $this->generateCrudResponse($message)
            : $this->generateQueryResponse($message);
    }

    private function generateQueryResponse(string $message): array
    {
        $this->sessionForget('ai_pending_action');
        $this->sessionForget('ai_crud_flow');

        if ($this->functionCallService->looksLikeCrudRequest($message)) {
            $reply = 'That looks like a create, update, or delete request. Please switch the dropdown to CRUD Assistant and send it again so I can safely run it through the Laravel handler.';
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'mode' => 'query',
            ];
        }

        $this->rememberUserPreference($message);
        $reply = $this->askModel($message, 'query');
        $this->rememberExchange($message, $reply);

        return [
            'response' => $reply,
            'mode' => 'query',
        ];
    }

    private function generateCrudResponse(string $message): array
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
                'redirect_url' => $result['redirect_url'] ?? null,
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

        if ($pendingAction) {
            $reply = 'Please reply "yes" to confirm or "cancel" to stop this action.';
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'requires_confirmation' => true,
            ];
        }

        $pendingFlow = $this->sessionValue('ai_crud_flow');

        if ($pendingFlow && $this->functionCallService->isCancellation($message)) {
            $this->sessionForget('ai_crud_flow');
            $reply = 'Cancelled. I did not change your journal.';
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'action_cancelled' => true,
            ];
        }

        if ($pendingFlow) {
            return $this->handlePlannedResult(
                $this->functionCallService->continueFlow($pendingFlow, $message),
                $message
            );
        }

        $action = $this->functionCallService->planAction($message, $this->sessionValue('ai_last_entry_id'));

        if ($action) {
            return $this->handlePlannedResult($action, $message);
        }

        if ($this->functionCallService->looksLikeCrudRequest($message)) {
            $reply = 'I can do that in CRUD Assistant mode, but I need the command in a clearer format. Try: Create a new entry titled "meow" content "hehehe" mood Happy category Personal.';
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'mode' => 'crud',
            ];
        }

        $this->rememberUserPreference($message);
        $reply = $this->askModel($message, 'crud');
        $this->rememberExchange($message, $reply);

        return [
            'response' => $reply,
            'mode' => 'crud',
        ];
    }

    private function normalizeMode(string $mode): string
    {
        return $mode === 'crud' ? 'crud' : 'query';
    }

    private function handlePlannedResult(array $result, string $message): array
    {
        if ($result['clear_flow'] ?? false) {
            $this->sessionForget('ai_crud_flow');
        }

        if (isset($result['flow'])) {
            $this->sessionPut('ai_crud_flow', $result['flow']);
        }

        if (isset($result['response']) && ! isset($result['action'])) {
            $this->rememberExchange($message, $result['response']);

            return [
                'response' => $result['response'],
            ];
        }

        $action = $result['action'] ?? $result;

        if ($action['requires_confirmation'] ?? false) {
            $reply = $this->functionCallService->confirmationText($action);
            $this->sessionPut('ai_pending_action', $action);
            $this->rememberExchange($message, $reply);

            return [
                'response' => $reply,
                'requires_confirmation' => true,
            ];
        }

        $execution = $this->functionCallService->executeAction($action);
        $this->rememberExchange($message, $execution['response']);

        return [
            'response' => $execution['response'],
            'action_executed' => true,
            'refresh' => (bool) ($execution['refresh'] ?? false),
            'redirect_url' => $execution['redirect_url'] ?? null,
            'entry_id' => $execution['entry_id'] ?? null,
        ];
    }

    public function savedMessages(int $limit = 50, ?string $mode = null): array
    {
        $sessionId = $this->sessionId();

        if (!$sessionId) {
            return [];
        }

        try {
            $messages = AIChatMessage::where('session_id', $sessionId)
                ->latest()
                ->take($mode ? $limit * 4 : $limit)
                ->get()
                ->reverse()
                ->filter(fn (AIChatMessage $message) => ! $mode || data_get($message->metadata, 'mode') === $this->normalizeMode($mode))
                ->take($limit)
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

        return $this->sessionHistoryMessages($limit, $mode);
    }

    private function askModel(string $message, string $mode = 'query'): string
    {
        $apiKeys = $this->configuredGeminiKeys();

        if ($apiKeys === []) {
            throw new RuntimeException('Gemini API key is missing. Add GEMINI_API_KEY, GEMINI_API_KEY_BACKUP, or GEMINI_API_KEY_BACKUP_2 to .env.');
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $facts = $this->functionCallService->journalFactsFor($message);
        $systemPrompt = $this->promptService->buildSystemPrompt($this->factsWithPreferences($facts), mode: $mode);
        $contents = $this->conversationContents($message, $mode);
        $lastError = null;

        foreach ($apiKeys as $apiKey) {
            try {
                return $this->requestGemini($apiKey, $model, $systemPrompt, $contents);
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        throw new RuntimeException(
            'The AI service could not respond right now. Please check your Gemini keys or quotas, then try again.',
            0,
            $lastError
        );
    }

    private function requestGemini(string $apiKey, string $model, string $systemPrompt, array $contents): string
    {
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
                'contents' => $contents,
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

    private function configuredGeminiKeys(): array
    {
        return collect([
            config('services.gemini.key'),
            config('services.gemini.backup_key'),
            config('services.gemini.backup_key_2'),
        ])
            ->filter(fn (mixed $key) => $this->isUsableGeminiKey($key))
            ->map(fn (string $key) => trim($key))
            ->unique()
            ->values()
            ->all();
    }

    private function rememberUserPreference(string $message): void
    {
        if (! request()->hasSession()) {
            return;
        }

        if (! preg_match('/\b(?:remember\s+)?(?:that\s+)?i\s+(?:prefer|like|want)\s+(.+)/i', $message, $matches)) {
            return;
        }

        $preference = trim($matches[1], " \t\n\r\0\x0B.,");

        if ($preference === '') {
            return;
        }

        $preferences = collect($this->sessionValue('ai_user_preferences', []))
            ->push($preference)
            ->unique()
            ->take(-5)
            ->values()
            ->all();

        $this->sessionPut('ai_user_preferences', $preferences);
    }

    private function factsWithPreferences(string $facts): string
    {
        $preferences = $this->sessionValue('ai_user_preferences', []);

        if ($preferences === []) {
            return $facts;
        }

        return "User preferences this session:\n- ".implode("\n- ", $preferences)."\n\n".$facts;
    }

    private function isUsableGeminiKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $key = trim($key);

        return $key !== '' && ! in_array($key, [
            'our_key_here',
            'your_real_gemini_api_key',
            'your_real_gemini_api_key_here',
            'your_second_gemini_api_key_here',
            'your_third_gemini_api_key_here',
            'your_optional_second_gemini_api_key_here',
            'your_optional_third_gemini_api_key_here',
            'your_backup_gemini_api_key',
            'your_backup_gemini_api_key_here',
        ], true);
    }

    private function conversationContents(string $message, string $mode): array
    {
        $history = collect($this->contextHistory($mode))
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

        $historyKey = $this->modeHistoryKey($this->activeMode);
        $history = collect($this->sessionValue($historyKey, []))
            ->push(['role' => 'user', 'content' => $userMessage])
            ->push(['role' => 'assistant', 'content' => $assistantMessage])
            ->take(-10)
            ->values()
            ->all();

        $this->sessionPut($historyKey, $history);
        $this->saveMessage('user', $userMessage, ['mode' => $this->activeMode]);
        $this->saveMessage('assistant', $assistantMessage, ['mode' => $this->activeMode]);
    }

    private function contextHistory(string $mode): array
    {
        $saved = $this->savedMessages(10, $mode);

        if ($saved !== []) {
            return $saved;
        }

        return $this->sessionValue($this->modeHistoryKey($mode), []);
    }

    private function sessionHistoryMessages(int $limit, ?string $mode = null): array
    {
        $history = $mode
            ? $this->sessionValue($this->modeHistoryKey($mode), [])
            : array_merge(
                $this->sessionValue('ai_chat_history', []),
                $this->sessionValue($this->modeHistoryKey('query'), []),
                $this->sessionValue($this->modeHistoryKey('crud'), []),
            );

        return collect($history)
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

    private function modeHistoryKey(string $mode): string
    {
        return 'ai_chat_history_'.$this->normalizeMode($mode);
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
