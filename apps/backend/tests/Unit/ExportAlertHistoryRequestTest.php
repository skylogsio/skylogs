<?php

use App\Http\Requests\AlertRule\ExportAlertHistoryRequest;

it('normalizes millisecond from and to timestamps to seconds', function () {
    $fromSeconds = 1_767_225_600;
    $toSeconds = $fromSeconds + 1000;

    $request = ExportAlertHistoryRequest::create('/api/v1/alert-rule/history/507f1f77bcf86cd799439011/export', 'GET', [
        'from' => $fromSeconds * 1000,
        'to' => $toSeconds * 1000,
    ]);

    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated('from'))->toBe($fromSeconds)
        ->and($request->validated('to'))->toBe($toSeconds)
        ->and($request->exportFormat())->toBe('xlsx');
});

it('leaves second timestamps unchanged', function () {
    $fromSeconds = 1_767_225_600;
    $toSeconds = $fromSeconds + 1000;

    $request = ExportAlertHistoryRequest::create('/api/v1/alert-rule/history/507f1f77bcf86cd799439011/export', 'GET', [
        'from' => $fromSeconds,
        'to' => $toSeconds,
        'format' => 'csv',
    ]);

    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated('from'))->toBe($fromSeconds)
        ->and($request->validated('to'))->toBe($toSeconds)
        ->and($request->exportFormat())->toBe('csv');
});
