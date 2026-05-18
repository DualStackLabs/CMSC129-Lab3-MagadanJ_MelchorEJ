<?php

use App\Models\Category;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('destroy permanently deletes a trashed entry', function () {
    $category = Category::create([
        'name' => 'Personal',
        'color_theme' => 'pink',
    ]);

    $entry = Entry::create([
        'title' => 'Temporary Entry',
        'content' => 'This entry will be permanently deleted.',
        'mood' => 'Calm',
        'category_id' => $category->id,
    ]);

    $entry->delete();

    $this->delete(route('entries.forceDelete', $entry->id))
        ->assertRedirect(route('entries.trash'));

    expect(Entry::withTrashed()->find($entry->id))->toBeNull();
});
