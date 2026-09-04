<?php

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| Binds Laravel's TestCase to Feature and Unit Pest tests. Class-based
| PHPUnit tests in these directories are unchanged.
|
*/

use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * @return list<string>
 */
function laravelPaginatorStructure(): array
{
    return [
        'current_page',
        'data',
        'last_page',
        'per_page',
        'total',
    ];
}
