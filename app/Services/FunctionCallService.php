<?php

namespace App\Services;

use Illuminate\Http\Request as InternalRequest;
use Illuminate\Support\Str;

class FunctionCallService
{
    public function journalFactsFor(string $message): string
    {
        $result = $this->callToolRoute('GET', route('ai-tools.entries.facts', [], false));
        $factsData = $result['facts'] ?? [];
        $latest = $factsData['latest_entry'] ?? null;

        if ($latest && ($latest['id'] ?? null)) {
            $this->rememberLastEntry((int) $latest['id']);
        }

        $facts = [
            'Total entries: '.($factsData['total_entries'] ?? 0),
            'Favorite entries: '.($factsData['favorite_entries'] ?? 0),
        ];

        if ($this->mentions($message, ['mood', 'feeling', 'feelings', 'emotion', 'emotions'])) {
            $facts[] = 'Entries by mood: '.$this->formatCounts($factsData['entries_by_mood'] ?? []);
        }

        if ($this->mentions($message, ['category', 'categories', 'topic', 'topics'])) {
            $facts[] = 'Entries by category: '.$this->formatCounts($factsData['entries_by_category'] ?? []);
        }

        if ($this->mentions($message, ['recent', 'latest', 'last entry', 'newest'])) {
            $facts[] = 'Latest entry: '.($latest ? 'entry "'.$latest['title'].'" ('.($latest['created_at'] ?? 'Unknown date').')' : 'No entries yet.');
        }

        return implode("\n", $facts);
    }

    public function planAction(string $message, ?int $lastEntryId = null): ?array
    {
        return match ($this->crudIntent($message)) {
            'create' => $this->beginCreateFlow($message),
            'delete' => $this->beginDeleteFlow($message, $lastEntryId),
            'update' => $this->beginUpdateFlow($message, $lastEntryId),
            default => null,
        };
    }

    public function looksLikeCrudRequest(string $message): bool
    {
        return $this->crudIntent($message) !== null;
    }

    private function crudIntent(string $message): ?string
    {
        $normalized = strtolower(trim($message));

        if ($this->looksLikeEntryDraft($message)) {
            return 'create';
        }

        $intentPatterns = [
            'create' => [
                '/^(?:please\s+)?(?:create|add|make|log|record|save|insert|write)\b/i',
                '/\b(?:can you|could you|please|i want to|i need to|help me)\s+(?:create|add|make|log|record|save|insert|write)\b/i',
                '/\b(?:new journal entry|new entry|new task)\b/i',
            ],
            'delete' => [
                '/^(?:please\s+)?(?:delete|remove|trash|erase)\b/i',
                '/\b(?:can you|could you|please|i want to|i need to|help me)\s+(?:delete|remove|trash|erase)\b/i',
            ],
            'update' => [
                '/^(?:please\s+)?(?:update|change|edit|rename|mark|set)\b/i',
                '/\b(?:can you|could you|please|i want to|i need to|help me)\s+(?:update|change|edit|rename|mark|set)\b/i',
            ],
        ];

        foreach ($intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalized) === 1) {
                    return $intent;
                }
            }
        }

        return null;
    }

    private function looksLikeEntryDraft(string $message): bool
    {
        return $this->hasLabeledField($message, ['title', 'titled', 'called', 'named'])
            && $this->hasLabeledField($message, ['content', 'body', 'description'])
            && $this->hasLabeledField($message, ['mood'])
            && $this->hasLabeledField($message, ['category']);
    }

    private function hasLabeledField(string $message, array $labels): bool
    {
        foreach ($labels as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b\s*(?:is|to|as|:)?\s*["\']?[^,.]+/i', $message) === 1) {
                return true;
            }
        }

        return false;
    }

    public function continueFlow(array $flow, string $message): array
    {
        return match ($flow['type'] ?? null) {
            'create' => $this->continueCreateFlow($flow, $message),
            'update' => $this->continueUpdateFlow($flow, $message),
            'delete' => $this->continueDeleteFlow($flow, $message),
            default => [
                'response' => 'I lost track of that action. Please start the command again.',
                'clear_flow' => true,
            ],
        };
    }

    public function executeAction(array $action): array
    {
        return match ($action['type'] ?? null) {
            'create' => $this->executeCreate($action),
            'update' => $this->executeUpdate($action),
            'delete' => $this->executeDelete($action),
            default => [
                'response' => 'I could not execute that assistant action.',
                'refresh' => false,
            ],
        };
    }

    public function confirmationText(array $action): string
    {
        return match ($action['type'] ?? null) {
            'update' => 'Please confirm: update '.$this->targetLabel($action).' with '.$this->fieldSummary($action['fields'] ?? []).'? Reply "yes" to confirm or "cancel" to stop.',
            'delete' => 'Please confirm: move '.$this->targetLabel($action).' to the trash? Reply "yes" to confirm or "cancel" to stop.',
            default => 'Please confirm this action. Reply "yes" to confirm or "cancel" to stop.',
        };
    }

    public function isConfirmation(string $message): bool
    {
        return in_array(strtolower(trim($message)), ['yes', 'y', 'yep', 'confirm', 'proceed', 'go ahead', 'do it'], true);
    }

    public function isCancellation(string $message): bool
    {
        return in_array(strtolower(trim($message)), ['no', 'n', 'cancel', 'stop', 'never mind', 'nevermind'], true);
    }

    private function beginCreateFlow(string $message): array
    {
        $draft = $this->extractCreateFields($message, true);

        return $this->createFlowResponse($draft);
    }

    private function continueCreateFlow(array $flow, string $message): array
    {
        $draft = array_merge($flow['draft'] ?? [], $this->extractCreateFields($message));

        return $this->createFlowResponse($draft);
    }

    private function createFlowResponse(array $draft): array
    {
        $missing = collect(['title', 'content', 'mood', 'category'])
            ->reject(fn (string $field) => filled($draft[$field] ?? null))
            ->values()
            ->all();

        if ($missing !== []) {
            return [
                'response' => $this->missingCreateFieldsMessage($missing, $draft),
                'flow' => [
                    'type' => 'create',
                    'draft' => $draft,
                ],
            ];
        }

        return [
            'action' => [
                'type' => 'create',
                'requires_confirmation' => false,
                'fields' => [
                    'title' => Str::limit($draft['title'], 255, ''),
                    'content' => $draft['content'],
                    'mood' => Str::headline($draft['mood']),
                    'category' => Str::headline($draft['category']),
                    'location' => $draft['location'] ?? null,
                    'is_favorite' => (bool) ($draft['is_favorite'] ?? false),
                ],
            ],
            'clear_flow' => true,
        ];
    }

    private function beginUpdateFlow(string $message, ?int $lastEntryId): array
    {
        $fields = $this->extractUpdateFields($message);
        $target = $this->resolveTargetFromMessage($message, $lastEntryId);

        if (!$target) {
            return [
                'response' => 'What is the exact title of the entry you want to edit?',
                'flow' => [
                    'type' => 'update',
                    'fields' => $fields,
                ],
            ];
        }

        if ($target['ambiguous'] ?? false) {
            return $this->ambiguousTitleResponse('edit', $target['matches'], ['type' => 'update', 'fields' => $fields]);
        }

        if ($fields === []) {
            return [
                'response' => 'What should I change in "'.$target['entry']['title'].'"? You can say: title, content, mood, category, location, or favorite status.',
                'flow' => [
                    'type' => 'update',
                    'entry' => $target['entry'],
                    'fields' => [],
                ],
            ];
        }

        return [
            'action' => $this->updateAction($target['entry'], $fields),
        ];
    }

    private function continueUpdateFlow(array $flow, string $message): array
    {
        $entry = $flow['entry'] ?? null;
        $fields = array_merge($flow['fields'] ?? [], $this->extractUpdateFields($message));

        if (!$entry) {
            $target = $this->resolveTarget($message);

            if (!$target) {
                return [
                    'response' => 'I could not find an entry with that title. Please send the exact title.',
                    'flow' => $flow,
                ];
            }

            if ($target['ambiguous'] ?? false) {
                return $this->ambiguousTitleResponse('edit', $target['matches'], $flow);
            }

            $entry = $target['entry'];
        }

        if ($fields === []) {
            return [
                'response' => 'Found "'.$entry['title'].'". What should I change? Example: Mood: Happy or Content: Updated text.',
                'flow' => [
                    'type' => 'update',
                    'entry' => $entry,
                    'fields' => [],
                ],
            ];
        }

        return [
            'action' => $this->updateAction($entry, $fields),
            'clear_flow' => true,
        ];
    }

    private function beginDeleteFlow(string $message, ?int $lastEntryId): array
    {
        $target = $this->resolveTargetFromMessage($message, $lastEntryId);

        if (!$target) {
            return [
                'response' => 'What is the exact title of the entry you want to delete?',
                'flow' => [
                    'type' => 'delete',
                ],
            ];
        }

        if ($target['ambiguous'] ?? false) {
            return $this->ambiguousTitleResponse('delete', $target['matches'], ['type' => 'delete']);
        }

        return [
            'action' => $this->deleteAction($target['entry']),
        ];
    }

    private function continueDeleteFlow(array $flow, string $message): array
    {
        $target = $this->resolveTarget($message);

        if (!$target) {
            return [
                'response' => 'I could not find an entry with that title. Please send the exact title you want to delete.',
                'flow' => $flow,
            ];
        }

        if ($target['ambiguous'] ?? false) {
            return $this->ambiguousTitleResponse('delete', $target['matches'], $flow);
        }

        return [
            'action' => $this->deleteAction($target['entry']),
            'clear_flow' => true,
        ];
    }

    private function executeCreate(array $action): array
    {
        $result = $this->callToolRoute('POST', route('ai-tools.entries.store', [], false), $action['fields'] ?? []);

        if (!($result['ok'] ?? false)) {
            return $this->failedToolResponse($result, 'create the entry');
        }

        $entry = $result['entry'];
        $this->rememberLastEntry($entry['id']);

        return [
            'response' => 'Created entry "'.$entry['title'].'". The entry list will refresh now.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $entry['id'],
        ];
    }

    private function executeUpdate(array $action): array
    {
        $result = $this->callToolRoute('PATCH', route('ai-tools.entries.update', ['entry' => $action['entry_id']], false), $action['fields'] ?? []);

        if (!($result['ok'] ?? false)) {
            return $this->failedToolResponse($result, 'update the entry');
        }

        $entry = $result['entry'];
        $this->rememberLastEntry($entry['id']);

        return [
            'response' => 'Updated entry "'.$entry['title'].'". Changed: '.$this->fieldSummary($action['fields'] ?? []).'. The entry list will refresh now.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $entry['id'],
        ];
    }

    private function executeDelete(array $action): array
    {
        $result = $this->callToolRoute('DELETE', route('ai-tools.entries.destroy', ['entry' => $action['entry_id']], false));

        if (!($result['ok'] ?? false)) {
            return $this->failedToolResponse($result, 'delete the entry');
        }

        $this->forgetLastEntry();

        return [
            'response' => 'Moved entry "'.$result['entry']['title'].'" to the trash. The entry list will refresh now.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $result['entry']['id'],
        ];
    }

    private function updateAction(array $entry, array $fields): array
    {
        return [
            'type' => 'update',
            'requires_confirmation' => true,
            'entry_id' => $entry['id'],
            'target' => 'entry "'.$entry['title'].'"',
            'fields' => $fields,
        ];
    }

    private function deleteAction(array $entry): array
    {
        return [
            'type' => 'delete',
            'requires_confirmation' => true,
            'entry_id' => $entry['id'],
            'target' => 'entry "'.$entry['title'].'"',
        ];
    }

    private function resolveTargetFromMessage(string $message, ?int $lastEntryId): ?array
    {
        if ($lastEntryId && $this->mentionsCurrentEntryReference($message)) {
            return $this->resolveTarget((string) $lastEntryId, true);
        }

        $title = $this->extractTargetTitle($message);

        return $title ? $this->resolveTarget($title) : null;
    }

    private function resolveTarget(string $titleOrId, bool $isId = false): ?array
    {
        $payload = $isId || ctype_digit(trim($titleOrId))
            ? ['id' => trim($titleOrId)]
            : ['title' => $this->cleanTargetTitle($titleOrId)];

        $result = $this->callToolRoute('GET', route('ai-tools.entries.resolve', [], false), $payload);

        if (!($result['ok'] ?? false) || empty($result['matches'])) {
            return null;
        }

        if (count($result['matches']) > 1) {
            return [
                'ambiguous' => true,
                'matches' => $result['matches'],
            ];
        }

        return [
            'entry' => $result['matches'][0],
        ];
    }

    private function callToolRoute(string $method, string $uri, array $payload = []): array
    {
        $request = $method === 'GET'
            ? InternalRequest::create($uri, $method, $payload)
            : InternalRequest::create(
                $uri,
                $method,
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                json_encode($payload, JSON_THROW_ON_ERROR)
            );

        $request->headers->set('Accept', 'application/json');

        if ($method !== 'GET') {
            $request->merge($payload);
        }

        $originalRequest = app('request');

        try {
            $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
        } finally {
            app()->instance('request', $originalRequest);
        }

        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded)
            ? $decoded + ['status' => $response->getStatusCode()]
            : ['ok' => false, 'message' => 'Tool route returned an invalid response.', 'status' => $response->getStatusCode()];
    }

    private function failedToolResponse(array $result, string $action): array
    {
        return [
            'response' => 'I could not '.$action.': '.($result['message'] ?? 'unknown error'),
            'refresh' => false,
        ];
    }

    private function ambiguousTitleResponse(string $verb, array $matches, array $flow): array
    {
        $titles = collect($matches)
            ->take(5)
            ->map(fn (array $entry) => '"'.$entry['title'].'"')
            ->implode(', ');

        return [
            'response' => 'I found more than one matching entry to '.$verb.': '.$titles.'. Please send the exact title.',
            'flow' => $flow,
        ];
    }

    private function missingCreateFieldsMessage(array $missing, array $draft): string
    {
        $known = collect($draft)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value, $field) => $field.': '.($value === true ? 'yes' : $value))
            ->values()
            ->implode(', ');

        $message = 'Sure. Please provide '.implode(', ', $missing).' for the new entry.';

        if ($known !== '') {
            $message .= ' I already have: '.$known.'.';
        }

        $message .= ' You can reply like: Title: ... Content: ... Mood: ... Category: ...';

        return $message;
    }

    private function extractCreateFields(string $message, bool $strictLabels = false): array
    {
        return array_filter([
            'title' => $this->extractExplicitTitle($message),
            'content' => $strictLabels ? $this->extractField($message, ['content', 'body', 'description']) : $this->extractContent($message),
            'mood' => $strictLabels ? $this->extractField($message, ['mood']) : $this->extractMood($message),
            'category' => $strictLabels ? $this->extractField($message, ['category']) : $this->extractCategory($message),
            'location' => $this->extractField($message, ['location', 'place']),
            'is_favorite' => $this->mentions($message, ['favorite', 'favourite', 'star']) ? true : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function extractUpdateFields(string $message): array
    {
        $fields = [];

        if ($title = $this->extractRenameTitle($message) ?? $this->extractField($message, ['title'])) {
            $fields['title'] = Str::limit($title, 255, '');
        }

        if ($content = $this->extractContent($message)) {
            $fields['content'] = $content;
        }

        if ($mood = $this->extractMood($message)) {
            $fields['mood'] = Str::headline($mood);
        }

        if ($category = $this->extractCategory($message)) {
            $fields['category'] = Str::headline($category);
        }

        if ($location = $this->extractField($message, ['location', 'place'])) {
            $fields['location'] = $location;
        }

        $lower = strtolower($message);
        if ($this->mentions($lower, ['favorite', 'favourite', 'star'])) {
            $fields['is_favorite'] = ! $this->mentions($lower, ['unfavorite', 'unfavourite', 'not favorite', 'remove favorite']);
        }

        return $fields;
    }

    private function extractTargetTitle(string $message): ?string
    {
        if ($quoted = $this->firstQuotedText($message)) {
            return $quoted;
        }

        if (preg_match('/\b(title|content|body|description|mood|category|location|place|favorite|favourite)\s+(?:of|for)\s+(.+?)\s+\b(?:to|into|as|is)\b/i', $message, $matches)) {
            return $this->cleanTargetTitle($matches[2]);
        }

        if (preg_match('/\brename\b\s+(.+?)\s+\bto\b/i', $message, $matches)) {
            return $this->cleanTargetTitle($matches[1]);
        }

        if (preg_match('/\bchange\s+(?:the\s+)?title\s+(?:of|for)\s+(.+?)\s+\bto\b/i', $message, $matches)) {
            return $this->cleanTargetTitle($matches[1]);
        }

        $clean = strtolower($message);
        $clean = preg_replace('/\b(delete|remove|trash|edit|update|change|rename|mark)\b/i', ' ', $clean);
        $clean = preg_replace('/\b(the|an|a|journal|entry|task|to|as)\b/i', ' ', $clean);
        $clean = preg_replace('/\b(title|content|body|description|mood|category|location|place|favorite|favourite)\b.*$/i', ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $clean !== '' ? $clean : null;
    }

    private function cleanTargetTitle(string $title): string
    {
        $title = preg_replace('/\b(the|an|a|journal|entry|task)\b/i', ' ', $title);

        return trim(preg_replace('/\s+/', ' ', $title));
    }

    private function extractExplicitTitle(string $message): ?string
    {
        return $this->extractField($message, ['title', 'titled', 'called', 'named']);
    }

    private function extractRenameTitle(string $message): ?string
    {
        if (preg_match('/\brename\b.+?\bto\s+["\']?([^"\']+?)["\']?$/i', $message, $matches)) {
            return $this->cleanExtractedValue($matches[1]);
        }

        if (preg_match('/\bchange\s+(?:the\s+)?title\b.+?\bto\s+["\']?([^"\']+?)["\']?$/i', $message, $matches)) {
            return $this->cleanExtractedValue($matches[1]);
        }

        return null;
    }

    private function extractContent(string $message): ?string
    {
        return $this->extractField($message, ['content', 'body', 'description'])
            ?? $this->extractSaidContent($message);
    }

    private function extractMood(string $message): ?string
    {
        if (preg_match('/\bmood\b.*?\b(?:is|to|as)\s+["\']?([a-zA-Z ]+?)["\']?(?:$|[,.])/i', $message, $matches)) {
            return $this->cleanExtractedValue($matches[1]);
        }

        if (preg_match('/\bmood\s*(?:is|to|as|:)?\s*["\']?([a-zA-Z ]+?)["\']?(?:$|[,.])/i', $message, $matches)) {
            return $this->cleanExtractedValue($matches[1]);
        }

        foreach (['Happy', 'Focused', 'Stressed', 'Calm', 'Tired', 'Excited', 'Anxious', 'Grateful'] as $mood) {
            if (preg_match('/\b'.preg_quote($mood, '/').'\b/i', $message)) {
                return $mood;
            }
        }

        return null;
    }

    private function extractCategory(string $message): ?string
    {
        if ($category = $this->extractField($message, ['category'])) {
            return $category;
        }

        foreach ($this->categoryNames() as $name) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $message)) {
                return $name;
            }
        }

        return null;
    }

    private function extractField(string $message, array $labels): ?string
    {
        $stopLabels = 'title|titled|called|named|content|body|description|mood|category|location|place|favorite|favourite';

        foreach ($labels as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b\s*(?:is|to|as|:)?\s*["\']([^"\']+)["\']/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }

            if (preg_match('/\b'.preg_quote($label, '/').'\b\s*(?:is|to|as|:)\s*([^,.]+)/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }

            if (preg_match('/\b'.preg_quote($label, '/').'\b\s+(.+?)(?=\s+\b(?:'.$stopLabels.')\b|$)/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }
        }

        return null;
    }

    private function extractSaidContent(string $message): ?string
    {
        foreach ([
            '/\b(?:that\s+)?says?\s+["\']([^"\']+)["\']/i',
            '/\b(?:that\s+)?says?\s+(.+)/i',
            '/\bsaying\s+["\']([^"\']+)["\']/i',
            '/\bsaying\s+(.+)/i',
        ] as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }
        }

        return null;
    }

    private function cleanExtractedValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+\bwith\s+(mood|category|location|place|title|content|body|description)\b.*$/i', '', $value);
        $value = preg_replace('/\s+\b(category|mood|location|place|title|content|body|description)\b\s*(?:is|to|as|:).+$/i', '', $value);
        $value = trim($value, " \t\n\r\0\x0B.,");

        return $value !== '' ? $value : null;
    }

    private function firstQuotedText(string $message): ?string
    {
        if (preg_match('/["\']([^"\']+)["\']/', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function targetLabel(array $action): string
    {
        return $action['target'] ?? 'that entry';
    }

    private function fieldSummary(array $fields): string
    {
        if ($fields === []) {
            return 'no fields';
        }

        return collect($fields)
            ->map(fn ($value, $field) => $field.'='.($value === true ? 'yes' : ($value === false ? 'no' : $value)))
            ->values()
            ->implode(', ');
    }

    private function formatCounts(array $counts): string
    {
        if ($counts === []) {
            return 'none';
        }

        return collect($counts)
            ->map(fn ($total, $label) => "{$label}: {$total}")
            ->values()
            ->implode(', ');
    }

    private function categoryNames(): array
    {
        $result = $this->callToolRoute('GET', route('ai-tools.categories.index', [], false));

        return $result['categories'] ?? [];
    }

    private function mentions(string $message, array $needles): bool
    {
        $message = strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($message, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function mentionsCurrentEntryReference(string $message): bool
    {
        return preg_match('/\b(it|that|this)\b/i', $message) === 1
            || preg_match('/\b(the|this|that)\s+(entry|task|journal)\b/i', $message) === 1;
    }

    private function rememberLastEntry(int $entryId): void
    {
        if (request()->hasSession()) {
            session(['ai_last_entry_id' => $entryId]);
        }
    }

    private function forgetLastEntry(): void
    {
        if (request()->hasSession()) {
            session()->forget('ai_last_entry_id');
        }
    }
}
