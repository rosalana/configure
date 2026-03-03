<?php

namespace Rosalana\Configure\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected string $fixturesPath;

    protected function setUp(): void
    {
        // Must be set before parent::setUp() because defineEnvironment()
        // is called during app creation inside parent::setUp()
        $this->fixturesPath = sys_get_temp_dir() . '/rosalana-configure-tests-' . md5(static::class);
        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0777, true);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob($this->fixturesPath . '/*.php') as $file) {
            unlink($file);
        }
    }

    protected function defineEnvironment($app): void
    {
        $app->useConfigPath($this->fixturesPath);
    }

    protected function createConfig(string $name, string $content): void
    {
        file_put_contents($this->fixturesPath . '/' . $name, $content);
    }
}
