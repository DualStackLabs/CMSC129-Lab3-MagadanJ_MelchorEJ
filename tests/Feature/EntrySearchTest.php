<?php

use App\Models\Category;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('entry search matches title content mood location and category case insensitively', function () {
    $category = Category::create([
        'name' => 'Searchable Category',
        'color_theme' => 'pink',
    ]);

    Entry::create([
        'title' => 'Quiet Morning',
        'content' => 'This should match by body keyword.',
        'mood' => 'Curious',
        'location' => 'Rooftop',
        'category_id' => $category->id,
    ]);

    $this->get('/entries?search=body%20keyword')
        ->assertOk()
        ->assertSee('Quiet Morning');

    $this->get('/entries?search=curious')
        ->assertOk()
        ->assertSee('Quiet Morning');

    $this->get('/entries?search=rooftop')
        ->assertOk()
        ->assertSee('Quiet Morning');

    $this->get('/entries?search=searchable')
        ->assertOk()
        ->assertSee('Quiet Morning');
});
