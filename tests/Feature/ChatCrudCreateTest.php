<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('crud assistant creates an entry from complete labeled fields', function () {
    $response = $this->postJson('/chat-send', [
        'mode' => 'crud',
        'message' => 'Title: meow, Content: wooooo!, Mood: happy, Category: personal, location: home',
    ]);

    $response->assertOk()
        ->assertJson([
            'action_executed' => true,
            'refresh' => true,
        ]);

    $this->assertDatabaseHas('entries', [
        'title' => 'meow',
        'content' => 'wooooo!',
        'mood' => 'Happy',
        'location' => 'home',
    ]);

    expect(Entry::where('title', 'meow')->first()?->category?->name)->toBe('Personal');
});

test('entry tool route accepts json payloads directly', function () {
    $this->postJson('/api/ai-tools/entries', [
        'title' => 'direct meow',
        'content' => 'direct body',
        'mood' => 'happy',
        'category' => 'personal',
        'location' => 'home',
    ])->assertCreated()
        ->assertJsonPath('entry.title', 'direct meow');

    $this->assertDatabaseHas('entries', [
        'title' => 'direct meow',
        'content' => 'direct body',
        'mood' => 'Happy',
        'location' => 'home',
    ]);
});

test('query mode refuses complete labeled create drafts', function () {
    $this->postJson('/chat-send', [
        'mode' => 'query',
        'message' => 'Title: meow, Content: wooooo!, Mood: happy, Category: personal',
    ])->assertOk()
        ->assertJsonPath('mode', 'query')
        ->assertJsonPath('response', 'That looks like a create, update, or delete request. Please switch the dropdown to CRUD Assistant and send it again so I can safely run it through the Laravel handler.');

    $this->assertDatabaseMissing('entries', [
        'title' => 'meow',
        'content' => 'wooooo!',
    ]);
});
