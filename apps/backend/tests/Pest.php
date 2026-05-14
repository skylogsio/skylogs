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
