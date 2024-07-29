<?php

namespace MarkusBiggus\StateEngine\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../../../bootstrap/app.php'; // from the vendor subfolder

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
