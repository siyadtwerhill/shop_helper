<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            throw new \RuntimeException('Refusing to run tests against a non-test database.');
        }
    }
}
