<?php

use App\Services\FunctionCallService;

test('crud planner creates an entry action from plain labeled fields', function () {
    $action = app(FunctionCallService::class)->planAction(
        'create entry title meow content hehehe mood happy category personal'
    );

    expect($action)->not->toBeNull()
        ->and($action['action']['type'])->toBe('create')
        ->and($action['action']['fields']['title'])->toBe('meow')
        ->and($action['action']['fields']['content'])->toBe('hehehe')
        ->and($action['action']['fields']['mood'])->toBe('Happy')
        ->and($action['action']['fields']['category'])->toBe('Personal');
});

test('crud planner recognizes natural create phrasing', function () {
    $action = app(FunctionCallService::class)->planAction(
        'make a new entry called meow content hehehe mood happy category personal'
    );

    expect($action)->not->toBeNull()
        ->and($action['action']['type'])->toBe('create')
        ->and($action['action']['fields']['title'])->toBe('meow');
});

test('crud planner treats complete labeled entry fields as a create draft', function () {
    $action = app(FunctionCallService::class)->planAction(
        'Title: meow, Content: wooooo!, Mood: happy, Category: personal, location: home'
    );

    expect($action)->not->toBeNull()
        ->and($action['action']['type'])->toBe('create')
        ->and($action['action']['fields']['title'])->toBe('meow')
        ->and($action['action']['fields']['content'])->toBe('wooooo!')
        ->and($action['action']['fields']['mood'])->toBe('Happy')
        ->and($action['action']['fields']['category'])->toBe('Personal')
        ->and($action['action']['fields']['location'])->toBe('home');
});

test('crud intent does not catch read-only questions about past changes', function () {
    $service = app(FunctionCallService::class);

    expect($service->looksLikeCrudRequest('How many entries did I create last week?'))->toBeFalse()
        ->and($service->looksLikeCrudRequest('What changed in my latest entry?'))->toBeFalse();
});
