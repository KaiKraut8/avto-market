<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        // no test talks to a real server: every page asks ARSO for the weather, and a test that needs
        // an outside service fakes it (an unfaked request throws, which the weather widget shrugs off)
        Http::preventStrayRequests();
    }
}
