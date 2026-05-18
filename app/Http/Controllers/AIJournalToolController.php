<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIJournalToolController extends Controller
{
    public function context(Request $request): JsonResponse
    {
        $entries = Entry::with('category')
            ->latest()
            ->take($request->integer('limit', 50))
            ->get();

        $context = $entries->isEmpty()
            ? 'No journal entries found yet.'
            : $entries
                ->map(function (Entry $entry) {
                    return implode("\n", [
                        "Title: {$entry->title}",
                        'Date: '.($entry->created_at?->toDateString() ?? 'Unknown'),
                        'Category: '.($entry->category?->name ?? 'Uncategorized'),
                        'Mood: '.($entry->mood ?: 'Not set'),
                        'Location: '.($entry->location ?: 'Not set'),
                        'Favorite: '.($entry->is_favorite ? 'Yes' : 'No'),
                        'Content: '.Str::limit($entry->content, 1200),
                    ]);
                })
                ->implode("\n---\n");

        return response()->json([
            'ok' => true,
            'context' => $context,
        ]);
    }

    public function facts(): JsonResponse
    {
        $latest = Entry::with('category')->latest()->first();

        return response()->json([
            'ok' => true,
            'facts' => [
                'total_entries' => Entry::count(),
                'favorite_entries' => Entry::where('is_favorite', true)->count(),
                'entries_by_mood' => Entry::query()
                    ->selectRaw("COALESCE(NULLIF(mood, ''), 'Not set') as label, COUNT(*) as total")
                    ->groupByRaw("COALESCE(NULLIF(mood, ''), 'Not set')")
                    ->orderByDesc('total')
                    ->pluck('total', 'label'),
                'entries_by_category' => Category::query()
                    ->withCount('entries')
                    ->orderByDesc('entries_count')
                    ->get()
                    ->pluck('entries_count', 'name'),
                'latest_entry' => $latest ? $this->entryPayload($latest) : null,
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'categories' => Category::orderBy('name')->pluck('name')->values(),
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        if ($request->filled('id')) {
            $entry = Entry::with('category')->find($request->integer('id'));

            return response()->json([
                'ok' => true,
                'matches' => $entry ? [$this->entryPayload($entry)] : [],
            ]);
        }

        $title = trim((string) $request->query('title', ''));

        if ($title === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Missing entry title.',
                'matches' => [],
            ], 422);
        }

        $normalized = strtolower($title);
        $query = Entry::with('category')
            ->whereRaw('LOWER(title) = ?', [$normalized]);

        $exact = $query->latest()->get();

        if ($exact->count() === 1) {
            return response()->json([
                'ok' => true,
                'matches' => [$this->entryPayload($exact->first())],
            ]);
        }

        $matches = Entry::with('category')
            ->whereRaw('LOWER(title) LIKE ?', ['%'.$normalized.'%'])
            ->latest()
            ->take(5)
            ->get();

        if ($matches->isEmpty()) {
            $words = collect(preg_split('/\s+/', $normalized))
                ->filter(fn ($word) => strlen($word) > 2)
                ->values();

            $matches = $words->isEmpty()
                ? collect()
                : Entry::with('category')
                    ->where(function ($query) use ($words) {
                        foreach ($words as $word) {
                            $query->whereRaw('LOWER(title) LIKE ?', ['%'.$word.'%']);
                        }
                    })
                    ->latest()
                    ->take(5)
                    ->get();
        }

        return response()->json([
            'ok' => true,
            'matches' => $matches->map(fn (Entry $entry) => $this->entryPayload($entry))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'mood' => 'required|string|max:50',
            'category' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'is_favorite' => 'sometimes|boolean',
        ]);

        $category = $this->firstOrCreateCategory($validated['category']);

        $entry = Entry::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'mood' => Str::headline($validated['mood']),
            'location' => $validated['location'] ?? null,
            'is_favorite' => (bool) ($validated['is_favorite'] ?? false),
            'category_id' => $category->id,
        ]);

        return response()->json([
            'ok' => true,
            'entry' => $this->entryPayload($entry->fresh('category')),
        ], 201);
    }

    public function update(Request $request, Entry $entry): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'mood' => 'sometimes|required|string|max:50',
            'category' => 'sometimes|required|string|max:100',
            'location' => 'nullable|string|max:255',
            'is_favorite' => 'sometimes|boolean',
        ]);

        if (array_key_exists('category', $validated)) {
            $validated['category_id'] = $this->firstOrCreateCategory($validated['category'])->id;
            unset($validated['category']);
        }

        if (array_key_exists('mood', $validated)) {
            $validated['mood'] = Str::headline($validated['mood']);
        }

        $entry->update($validated);

        return response()->json([
            'ok' => true,
            'entry' => $this->entryPayload($entry->fresh('category')),
        ]);
    }

    public function destroy(Entry $entry): JsonResponse
    {
        $payload = $this->entryPayload($entry->load('category'));
        $entry->delete();

        return response()->json([
            'ok' => true,
            'entry' => $payload,
        ]);
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

    private function entryPayload(Entry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'content' => $entry->content,
            'mood' => $entry->mood,
            'category' => $entry->category?->name,
            'location' => $entry->location,
            'is_favorite' => (bool) $entry->is_favorite,
            'created_at' => $entry->created_at?->toDateString(),
        ];
    }
}
