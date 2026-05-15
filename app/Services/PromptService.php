<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PromptService
{
    public function buildSystemPrompt(?string $extraContext = null, int $entryLimit = 50): string
    {
        $journalContext = $this->journalContext($entryLimit);
        $factsContext = trim((string) $extraContext);

        return <<<PROMPT
You are the Daily Draft Assistant for a personal journal app.
Use the journal context below when answering questions about entries.
If the context does not contain the answer, say so instead of inventing details.
Use the previous conversation messages to resolve follow-up questions and pronouns.
Keep responses concise, supportive, and practical.

Journal facts:
{$factsContext}

Journal entries:
{$journalContext}
PROMPT;
    }

    public function journalContext(int $limit = 50): string
    {
        $entries = $this->recentEntries($limit);

        if ($entries->isEmpty()) {
            return 'No journal entries found yet.';
        }

        return $entries
            ->map(fn (Entry $entry) => $this->formatEntry($entry))
            ->implode("\n---\n");
    }

    public function recentEntries(int $limit = 50): Collection
    {
        return Entry::with('category')
            ->latest()
            ->take($limit)
            ->get();
    }

    private function formatEntry(Entry $entry): string
    {
        return implode("\n", [
            "Title: {$entry->title}",
            'Date: '.($entry->created_at?->toDateString() ?? 'Unknown'),
            'Category: '.($entry->category?->name ?? 'Uncategorized'),
            'Mood: '.($entry->mood ?: 'Not set'),
            'Location: '.($entry->location ?: 'Not set'),
            'Favorite: '.($entry->is_favorite ? 'Yes' : 'No'),
            'Content: '.Str::limit($entry->content, 1200),
        ]);
    }
}
