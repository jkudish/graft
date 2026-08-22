<?php

use Graft\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

pest()->tia()->always()->locally();

require_once __DIR__.'/Helpers.php';
