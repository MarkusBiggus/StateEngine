<?php

namespace MarkusBiggus\StateEngine\Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication,
        ArraySubsetAsserts;
}
