<?php

use App\Http\Controllers\V1\AlertRule\AlertingController;
use App\Models\User;
use Illuminate\Http\Request;

it('returns the first validation message when alert rule store validation fails', function () {
    $controller = app(AlertingController::class);

    $request = Request::create('/api/v1/alert-rule', 'POST', []);

    $result = $controller->Store($request);

    expect($result)->toBeArray()
        ->and($result['status'])->toBeFalse()
        ->and($result['message'])->toBeString()
        ->and($result['message'])->not->toBeEmpty();
});

it('returns validation errors over http without a server error', function () {
    $user = User::query()
        ->get()
        ->first(fn (User $user) => $user->hasRole('owner') || $user->hasRole('manager'));

    if ($user === null) {
        $this->markTestSkipped('Owner or manager user required.');
    }

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/alert-rule', [])
        ->assertSuccessful()
        ->assertJsonPath('status', false)
        ->assertJsonStructure(['status', 'message']);
});
