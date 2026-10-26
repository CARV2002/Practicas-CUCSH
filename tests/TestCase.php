<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pantallas usan @vite; las pruebas no deben depender de haber corrido `npm run build`.
        $this->withoutVite();
    }
}
