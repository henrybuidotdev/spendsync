<?php

namespace Tests;

use Database\Seeders\SystemCategorySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /** Feature tests start with the shared categories, like a real install after `migrate --seed`. */
    protected bool $seed = true;

    protected string $seeder = SystemCategorySeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }
}
