<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * The `array` cache store (CACHE_STORE=array in phpunit.xml) persists for the whole test-suite
     * process — RefreshDatabase resets the database between tests but never touches the cache — so
     * without this, a value cached by one test (e.g. Setting::get()'s 5-minute remember()) would leak
     * into every later test that reads the same cache key, even after the underlying DB row changed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
