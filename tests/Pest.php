<?php

declare(strict_types=1);

use LaravelGtm\SnowflakeSdk\Tests\TestCase;
use Saloon\Config;

Config::preventStrayRequests();

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature', 'Integration');
uses()->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidSql', function () {
    return $this->toBeString()
        ->not->toBeEmpty();
});
