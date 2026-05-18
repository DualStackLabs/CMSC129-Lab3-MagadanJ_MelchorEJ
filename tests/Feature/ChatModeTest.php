<?php

use App\Services\AIService;

test('web chat defaults to query mode', function () {
    $mock = \Mockery::mock(AIService::class);
    $mock->shouldReceive('generateResponse')
        ->once()
        ->with('How many entries do I have?', 'query')
        ->andReturn(['response' => 'You have 15 entries.']);

    app()->instance(AIService::class, $mock);

    $this->postJson('/chat-send', [
        'message' => 'How many entries do I have?',
    ])->assertOk()
        ->assertJson(['response' => 'You have 15 entries.']);
});

test('web chat passes crud mode to the assistant service', function () {
    $mock = \Mockery::mock(AIService::class);
    $mock->shouldReceive('generateResponse')
        ->once()
        ->with('Update entry #3 mood to Happy', 'crud')
        ->andReturn(['response' => 'Please confirm this update.', 'requires_confirmation' => true]);

    app()->instance(AIService::class, $mock);

    $this->postJson('/chat-send', [
        'message' => 'Update entry #3 mood to Happy',
        'mode' => 'crud',
    ])->assertOk()
        ->assertJson([
            'response' => 'Please confirm this update.',
            'requires_confirmation' => true,
        ]);
});

test('assistant api defaults to crud mode', function () {
    $mock = \Mockery::mock(AIService::class);
    $mock->shouldReceive('generateResponse')
        ->once()
        ->with('Delete entry #4', 'crud')
        ->andReturn(['response' => 'Please confirm this delete.', 'requires_confirmation' => true]);

    app()->instance(AIService::class, $mock);

    $this->postJson('/api/ai-assistant', [
        'message' => 'Delete entry #4',
    ])->assertOk()
        ->assertJson([
            'response' => 'Please confirm this delete.',
            'requires_confirmation' => true,
        ]);
});
