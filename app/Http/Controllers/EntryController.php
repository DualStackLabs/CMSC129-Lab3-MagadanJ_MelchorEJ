<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Entry;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class EntryController extends Controller
{
    // 1. SHOW THE DASHBOARD (Read)
    // 1. SHOW THE DASHBOARD (Read)
    public function index(Request $request)
    {
        $query = Entry::with('category'); // Eager load category

        // Search feature
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $term = '%' . mb_strtolower($search) . '%';

            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(content) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(mood, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$term])
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($term) {
                        $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$term]);
                    });
            });
        }

        // Mood Filter
        if ($request->filled('mood')) {
            $query->where('mood', $request->mood);
        }

        // NEW: Category Filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->get('is_favorite') === '1') {
            $query->where('is_favorite', true);
        }

        $entries = $query->latest()->get();
        $categories = Category::orderBy('name')->get(); // Fetch categories for the search bar
        $moods = Entry::query()
            ->whereNotNull('mood')
            ->where('mood', '<>', '')
            ->distinct()
            ->orderBy('mood')
            ->pluck('mood');

        return view('entries.index', compact('entries', 'categories', 'moods'));
    }

    // 2. SHOW THE CREATE FORM
    public function create()
    {
        $categories = Category::all(); // Fetch all categories
        return view('entries.create', compact('categories'));
    }

    // 3. SAVE THE NEW ENTRY (Create)
    // 3. SAVE THE NEW ENTRY (Create)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'mood' => 'required',
            'location' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,png,jpeg|max:10240', // Validation is here
            'category_id' => 'required|exists:categories,id'
        ]);

        // --- ADD THIS LOGIC ---
        if ($request->hasFile('image')) {
            // Stores in storage/app/public/entries
            $path = $request->file('image')->store('entries', 'public');
            $validated['image'] = $path;
        }
        // ----------------------

        $validated['is_favorite'] = $request->has('is_favorite');

        Entry::create($validated);

        return redirect('/entries')->with('success', 'Journal entry saved successfully!');
    }

     public function edit(Entry $entry)
    {
        $categories = Category::all(); // Fetch all categories
        return view('entries.edit', compact('entry', 'categories'));
    }


    // 5. SAVE THE UPDATES (Update)
    public function update(Request $request, Entry $entry)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'mood' => 'required',
            'location' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,png,jpeg|max:10240',
           'category_id' => 'required|exists:categories,id'
        ]);

        // --- ADD THIS LOGIC ---
        if ($request->hasFile('image')) {
            // Optional: Delete old image if it exists to save space
            if ($entry->image) {
                \Storage::disk('public')->delete($entry->image);
            }
            
            $path = $request->file('image')->store('entries', 'public');
            $validated['image'] = $path;
        }
        // ----------------------

        $validated['is_favorite'] = $request->has('is_favorite');

        $entry->update($validated);

        return redirect('/entries')->with('success', 'Journal entry updated!');
    }

    // 6. SOFT DELETE THE ENTRY (Delete)
    public function destroy(Entry $entry)
    {
        $entry->delete();
        return redirect('/entries')->with('success', 'Entry moved to trash.');
    }

    public function trash()
    {
        // This fetches ONLY the items that were soft-deleted
        $trashedEntries = Entry::onlyTrashed()->latest()->get();
        
        return view('entries.trash', compact('trashedEntries'));
    }

    public function restore($id)
    {
        // We use findOrFail with onlyTrashed because a normal find() won't see it!
        $entry = Entry::onlyTrashed()->findOrFail($id);
        $entry->restore();

        return redirect()->route('entries.index')->with('success', 'Entry restored successfully!');
    }

    public function forceDelete($id)
    {
        $entry = Entry::onlyTrashed()->findOrFail($id);

        if ($entry->image) {
            Storage::disk('public')->delete($entry->image);
        }

        $entry->forceDelete();

        return redirect()->route('entries.trash')->with('success', 'Entry permanently deleted.');
    }
}
