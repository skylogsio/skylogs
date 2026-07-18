<?php

use App\Http\Requests\AlertRule\AlertStatusRequest;

it('normalizes millisecond timestamps to seconds', function () {
    $fromSeconds = 1_767_225_600;
    $toSeconds = $fromSeconds + 1000;

    $request = AlertStatusRequest::create('/api/v1/alert-rule/status', 'GET', [
        'alertRuleIds' => ['507f1f77bcf86cd799439011'],
        'fromTime' => $fromSeconds * 1000,
        'toTime' => $toSeconds * 1000,
    ]);

    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated('fromTime'))->toBe($fromSeconds)
        ->and($request->validated('toTime'))->toBe($toSeconds);
});

it('leaves second timestamps unchanged', function () {
    $fromSeconds = 1_767_225_600;
    $toSeconds = $fromSeconds + 1000;

    $request = AlertStatusRequest::create('/api/v1/alert-rule/status', 'GET', [
        'alertRuleIds' => ['507f1f77bcf86cd799439011'],
        'fromTime' => $fromSeconds,
        'toTime' => $toSeconds,
    ]);

    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated('fromTime'))->toBe($fromSeconds)
        ->and($request->validated('toTime'))->toBe($toSeconds);
});
