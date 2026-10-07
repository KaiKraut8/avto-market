<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wipes the database: refuse to run anywhere but the test database
        $database = DB::connection()->getDatabaseName();
        if ($database !== 'kai_test') {
            throw new \RuntimeException("Tests must run on kai_test, not \"{$database}\". Check phpunit.xml.");
        }
    }
}
