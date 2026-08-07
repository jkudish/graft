<?php

use Graft\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Integration');

pest()->tia()->always()->locally();
