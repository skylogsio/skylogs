<?php

use App\Http\Requests\AlertRule\AlertRuleIndexRequest;

it('uses pinned then id sort when no sort query param is sent', function () {
    $request = AlertRuleIndexRequest::create('/api/v1/alert-rule', 'GET');
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->mongoSort())->toBe(['isPinned' => -1, '_id' => 1]);
});

it('sorts by name ascending when sortBy is name', function () {
    $request = AlertRuleIndexRequest::create('/api/v1/alert-rule', 'GET', [
        'sortBy' => 'name',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->mongoSort())->toBe(['name' => 1, '_id' => 1]);
});

it('sorts by name descending when sortDir is desc', function () {
    $request = AlertRuleIndexRequest::create('/api/v1/alert-rule', 'GET', [
        'sortBy' => 'name',
        'sortDir' => 'desc',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->mongoSort())->toBe(['name' => -1, '_id' => 1]);
});

it('keeps the default sort when only sortDir is sent', function () {
    $request = AlertRuleIndexRequest::create('/api/v1/alert-rule', 'GET', [
        'sortDir' => 'desc',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->mongoSort())->toBe(['isPinned' => -1, '_id' => 1]);
});

it('uses the default sort when sortBy is not a sortable field', function () {
    $request = AlertRuleIndexRequest::create('/api/v1/alert-rule', 'GET', [
        'sortBy' => 'state',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->mongoSort())->toBe(['isPinned' => -1, '_id' => 1]);
});
