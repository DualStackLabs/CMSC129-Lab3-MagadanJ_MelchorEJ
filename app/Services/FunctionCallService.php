<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FunctionCallService
{
    public function journalFactsFor(string $message): string
    {
        $facts = [
            'Total entries: '.$this->totalEntries(),
            'Favorite entries: '.$this->favoriteEntries(),
        ];

        if ($this->mentions($message, ['mood', 'feeling', 'feelings', 'emotion', 'emotions'])) {
            $facts[] = 'Entries by mood: '.$this->formatCounts($this->entriesByMood());
        }

        if ($this->mentions($message, ['category', 'categories', 'topic', 'topics'])) {
            $facts[] = 'Entries by category: '.$this->formatCounts($this->entriesByCategory());
        }

        if ($this->mentions($message, ['recent', 'latest', 'last entry', 'newest'])) {
            $facts[] = 'Latest entry: '.$this->latestEntrySummary();
        }

        return implode("\n", $facts);
    }

    public function planAction(string $message, ?int $lastEntryId = null): ?array
    {
        $normalized = strtolower($message);

        if ($this->mentions($normalized, ['create', 'add', 'write a new', 'new entry'])) {
            return $this->planCreate($message);
        }

        if ($this->mentions($normalized, ['delete', 'remove', 'trash'])) {
            return $this->planDelete($message, $lastEntryId);
        }

        if ($this->mentions($normalized, ['update', 'change', 'edit', 'rename', 'mark'])) {
            return $this->planUpdate($message, $lastEntryId);
        }

        return null;
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
        $message = strtolower(trim($message));

        return in_array($message, ['yes', 'y', 'yep', 'confirm', 'proceed', 'go ahead', 'do it'], true);
    }

    public function isCancellation(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, ['no', 'n', 'cancel', 'stop', 'never mind', 'nevermind'], true);
    }

    private function planCreate(string $message): array
    {
        $category = $this->extractCategory($message) ?? 'Personal';
        $content = $this->extractField($message, ['content', 'body', 'description'])
            ?? $this->extractSaidContent($message)
            ?? $this->extractQuotedAfter($message, ['about'])
            ?? $this->extractAfterColon($message)
            ?? $this->cleanCreateRequest($message);

        $title = $this->extractTitle($message)
            ?? $this->titleFromContent($content);

        return [
            'type' => 'create',
            'requires_confirmation' => false,
            'fields' => [
                'title' => Str::limit($title, 120, ''),
                'content' => $content,
                'mood' => $this->extractMood($message) ?? 'Focused',
                'location' => $this->extractField($message, ['location']) ?? $this->extractLocation($message),
                'is_favorite' => $this->mentions(strtolower($message), ['favorite', 'favourite', 'star']),
                'category' => $category,
            ],
        ];
    }

    private function planUpdate(string $message, ?int $lastEntryId): array
    {
        $entry = $this->findTargetEntry($message, $lastEntryId);

        if (!$entry) {
            return [
                'type' => 'clarify',
                'response' => 'Which entry should I update? Give me the entry ID or title, like: update entry #3 mood to Happy.',
            ];
        }

        $fields = [];

        if ($title = $this->extractField($message, ['title', 'rename'])) {
            $fields['title'] = Str::limit($title, 255, '');
        }

        if ($content = $this->extractField($message, ['content', 'body', 'description'])) {
            $fields['content'] = $content;
        }

        if ($mood = $this->extractMood($message)) {
            $fields['mood'] = $mood;
        }

        if ($category = $this->extractCategory($message)) {
            $fields['category'] = $category;
        }

        if ($location = $this->extractField($message, ['location'])) {
            $fields['location'] = $location;
        }

        $lower = strtolower($message);
        if ($this->mentions($lower, ['favorite', 'favourite', 'star'])) {
            $fields['is_favorite'] = ! $this->mentions($lower, ['unfavorite', 'unfavourite', 'not favorite', 'remove favorite']);
        }

        if ($fields === []) {
            return [
                'type' => 'clarify',
                'response' => 'What should I change for '.$this->describeEntry($entry).'? You can change title, content, mood, category, location, or favorite status.',
            ];
        }

        return [
            'type' => 'update',
            'requires_confirmation' => true,
            'entry_id' => $entry->id,
            'target' => $this->describeEntry($entry),
            'fields' => $fields,
        ];
    }

    private function planDelete(string $message, ?int $lastEntryId): array
    {
        $entry = $this->findTargetEntry($message, $lastEntryId);

        if (!$entry) {
            return [
                'type' => 'clarify',
                'response' => 'Which entry should I move to trash? Give me the entry ID or title, like: delete entry #3.',
            ];
        }

        return [
            'type' => 'delete',
            'requires_confirmation' => true,
            'entry_id' => $entry->id,
            'target' => $this->describeEntry($entry),
        ];
    }

    private function executeCreate(array $action): array
    {
        $fields = $action['fields'];
        $category = $this->firstOrCreateCategory($fields['category'] ?? 'Personal');

        $entry = Entry::create([
            'title' => $fields['title'],
            'content' => $fields['content'],
            'mood' => $fields['mood'] ?? 'Focused',
            'location' => $fields['location'] ?? null,
            'is_favorite' => (bool) ($fields['is_favorite'] ?? false),
            'category_id' => $category->id,
        ]);

        $this->rememberLastEntry($entry->id);

        return [
            'response' => 'Created '.$this->describeEntry($entry->fresh('category')).'. The entry list will refresh so you can see it.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $entry->id,
        ];
    }

    private function executeUpdate(array $action): array
    {
        $entry = Entry::with('category')->find($action['entry_id']);

        if (!$entry) {
            return [
                'response' => 'I could not find that entry anymore, so nothing was updated.',
                'refresh' => false,
            ];
        }

        $fields = $action['fields'] ?? [];
        $updates = collect($fields)->except('category')->all();

        if (isset($fields['category'])) {
            $updates['category_id'] = $this->firstOrCreateCategory($fields['category'])->id;
        }

        $entry->update($updates);
        $entry = $entry->fresh('category');
        $this->rememberLastEntry($entry->id);

        return [
            'response' => 'Updated '.$this->describeEntry($entry).'. Changed: '.$this->fieldSummary($fields).'. The entry list will refresh now.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $entry->id,
        ];
    }

    private function executeDelete(array $action): array
    {
        $entry = Entry::with('category')->find($action['entry_id']);

        if (!$entry) {
            return [
                'response' => 'I could not find that entry anymore, so nothing was moved to trash.',
                'refresh' => false,
            ];
        }

        $description = $this->describeEntry($entry);
        $entry->delete();
        $this->forgetLastEntry();

        return [
            'response' => 'Moved '.$description.' to the trash. The entry list will refresh now.',
            'refresh' => true,
            'redirect_url' => route('entries.index'),
            'entry_id' => $entry->id,
        ];
    }

    private function findTargetEntry(string $message, ?int $lastEntryId): ?Entry
    {
        if (preg_match('/#\s*(\d+)|\b(?:id|entry)\s+(\d+)\b/i', $message, $matches)) {
            $id = (int) ($matches[1] ?: $matches[2]);

            return Entry::with('category')->find($id);
        }

        $quoted = $this->firstQuotedText($message);
        if ($quoted) {
            $entry = Entry::with('category')
                ->whereRaw('LOWER(title) = ?', [strtolower($quoted)])
                ->orWhere('title', 'like', '%'.$quoted.'%')
                ->latest()
                ->first();

            if ($entry) {
                return $entry;
            }
        }

        if ($lastEntryId && $this->mentions(strtolower($message), ['it', 'that', 'this', 'the entry'])) {
            return Entry::with('category')->find($lastEntryId);
        }

        $candidate = trim(preg_replace('/\b(update|change|edit|rename|mark|delete|remove|trash|entry|journal|to|as|the)\b/i', ' ', $message));
        $candidate = trim(preg_replace('/\s+/', ' ', $candidate));

        if ($candidate !== '') {
            return Entry::with('category')
                ->where('title', 'like', '%'.$candidate.'%')
                ->latest()
                ->first();
        }

        return null;
    }

    private function totalEntries(): int
    {
        return Entry::count();
    }

    private function favoriteEntries(): int
    {
        return Entry::where('is_favorite', true)->count();
    }

    private function entriesByMood(): Collection
    {
        return Entry::query()
            ->selectRaw("COALESCE(NULLIF(mood, ''), 'Not set') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(mood, ''), 'Not set')")
            ->orderByDesc('total')
            ->pluck('total', 'label');
    }

    private function entriesByCategory(): Collection
    {
        return Category::query()
            ->withCount('entries')
            ->orderByDesc('entries_count')
            ->get()
            ->pluck('entries_count', 'name');
    }

    private function latestEntrySummary(): string
    {
        $entry = Entry::with('category')->latest()->first();

        if (!$entry) {
            return 'No entries yet.';
        }

        $this->rememberLastEntry($entry->id);

        return $this->describeEntry($entry);
    }

    private function describeEntry(Entry $entry): string
    {
        $date = $entry->created_at?->toDateString() ?? 'Unknown date';
        $category = $entry->category?->name ?? 'Uncategorized';

        return "entry #{$entry->id} \"{$entry->title}\" ({$date}, {$category})";
    }

    private function targetLabel(array $action): string
    {
        return $action['target'] ?? ('entry #'.($action['entry_id'] ?? '?'));
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

    private function formatCounts(Collection $counts): string
    {
        if ($counts->isEmpty()) {
            return 'none';
        }

        return $counts
            ->map(fn ($total, $label) => "{$label}: {$total}")
            ->values()
            ->implode(', ');
    }

    private function firstOrCreateCategory(string $name): Category
    {
        $name = Str::headline(trim($name));

        return Category::firstOrCreate(
            ['name' => $name ?: 'Personal'],
            ['color_theme' => $this->themeForCategory($name)]
        );
    }

    private function themeForCategory(string $name): string
    {
        $themes = ['pink', 'blue', 'emerald', 'amber', 'indigo', 'slate'];

        return $themes[crc32(strtolower($name)) % count($themes)];
    }

    private function extractTitle(string $message): ?string
    {
        return $this->extractField($message, ['called', 'titled', 'named', 'title']);
    }

    private function extractMood(string $message): ?string
    {
        foreach (['Happy', 'Focused', 'Stressed', 'Calm', 'Tired', 'Excited', 'Anxious', 'Grateful'] as $mood) {
            if (preg_match('/\b'.preg_quote($mood, '/').'\b/i', $message)) {
                return $mood;
            }
        }

        return $this->extractField($message, ['mood']);
    }

    private function extractCategory(string $message): ?string
    {
        $category = $this->extractField($message, ['category']);
        if ($category) {
            return $category;
        }

        foreach (Category::pluck('name') as $name) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $message)) {
                return $name;
            }
        }

        return null;
    }

    private function extractLocation(string $message): ?string
    {
        if (preg_match('/\bat\s+["\']([^"\']+)["\']/i', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractField(string $message, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b\s*(?:is|to|as|:)?\s*["\']([^"\']+)["\']/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }

            if (preg_match('/\b'.preg_quote($label, '/').'\b\s*(?:is|to|as|:)\s*([^,.]+)/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }
        }

        return null;
    }

    private function extractSaidContent(string $message): ?string
    {
        $patterns = [
            '/\b(?:that\s+)?says?\s+["\']([^"\']+)["\']/i',
            '/\b(?:that\s+)?says?\s+(.+)/i',
            '/\bsaying\s+["\']([^"\']+)["\']/i',
            '/\bsaying\s+(.+)/i',
            '/\babout\s+["\']([^"\']+)["\']/i',
            '/\babout\s+(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }
        }

        return null;
    }

    private function cleanCreateRequest(string $message): string
    {
        $value = preg_replace('/\b(create|add|write)\b\s+(a\s+|an\s+|new\s+)?(journal\s+)?entry\s*(for me)?/i', '', $message);

        return $this->cleanExtractedValue($value ?: $message) ?: 'New journal entry';
    }

    private function cleanExtractedValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+\bwith\s+(mood|category|location|title|content)\b.*$/i', '', $value);
        $value = preg_replace('/\s+\bin\s+(the\s+)?(mood|category)\b.*$/i', '', $value);
        $value = trim($value, " \t\n\r\0\x0B.,");

        return $value !== '' ? $value : null;
    }

    private function titleFromContent(string $content): string
    {
        $title = Str::headline(Str::limit($content, 45, ''));

        return $title !== '' ? $title : 'New Journal Entry';
    }

    private function extractQuotedAfter(string $message, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b\s+["\']([^"\']+)["\']/i', $message, $matches)) {
                return $this->cleanExtractedValue($matches[1]);
            }
        }

        return null;
    }

    private function extractAfterColon(string $message): ?string
    {
        if (preg_match('/:\s*([^,.]+)/', $message, $matches)) {
            return $this->cleanExtractedValue($matches[1]);
        }

        return null;
    }

    private function firstQuotedText(string $message): ?string
    {
        if (preg_match('/["\']([^"\']+)["\']/', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
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
