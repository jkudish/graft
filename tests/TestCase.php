<?php

namespace Graft\Tests;

use Graft\Facades\Git;
use Graft\Facades\GitHub;
use Graft\GraftServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            GraftServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Git' => Git::class,
            'GitHub' => GitHub::class,
        ];
    }
}
