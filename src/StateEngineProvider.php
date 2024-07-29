<?php

namespace MarkusBiggus\StateEngine;

/**
 * MIT License
 *
 * Copyright (c) 2024 Mark Charles
 */
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

use MarkusBiggus\StateEngine\Workflow\Examples\DynoWorkflow;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Workflow\StateEngine;

class StateEngineProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StateEngine::class, function () {
            return function (WorkflowContract $Workflow): StateEngine {
                return new StateEngine($Workflow);
            };
        });

        // Dynamic Workflow for testing
        $this->app->singleton(DynoWorkflow::class, function () {
            return new DynoWorkflow();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();

        $this->publishes([
            __DIR__ . '/../tests/Feature/StateEngineControllerTest.php' => base_path('tests/StateEngine/Feature/StateEngineControllerTest.php'),
            __DIR__ . '/../tests/Feature/ForkMergeTest.php' => base_path('tests/StateEngine/Feature/ForkMergeTest.php'),
            __DIR__ . '/../tests/Feature/ForkSyncTest.php' => base_path('tests/StateEngine/Feature/ForkSyncTest.php'),
            __DIR__ . '/../tests/Feature/SplitMergeTest.php' => base_path('tests/StateEngine/Feature/SplitMergeTest.php'),
            __DIR__ . '/../tests/Feature/SplitSyncTest.php' => base_path('tests/StateEngine/Feature/SplitSyncTest.php'),
            __DIR__ . '/../tests/Feature/IdleStopTest.php' => base_path('tests/StateEngine/Feature/IdleStopTest.php'),
            __DIR__ . '/../tests/Feature/SEQ18aTest.php' => base_path('tests/StateEngine/Feature/SEQ18aTest.php'),
            __DIR__ . '/../tests/Feature/SEQ18bTest.php' => base_path('tests/StateEngine/Feature/SEQ18bTest.php'),

            __DIR__ . '/../tests/Unit/ControllerExceptionTest.php' => base_path('tests/StateEngine/Unit/ControllerExceptionTest.php'),
            __DIR__ . '/../tests/Unit/ReferenceApplicationTest.php' => base_path('tests/StateEngine/Unit/ReferenceApplicationTest.php'),
            __DIR__ . '/../tests/Unit/ReferenceModelTest.php' => base_path('tests/StateEngine/Unit/ReferenceModelTest.php'),
            __DIR__ . '/../tests/Unit/PipelineModelTest.php' => base_path('tests/StateEngine/Unit/PipelineModelTest.php'),
            __DIR__ . '/../tests/Unit/PipelineApplicationTest.php' => base_path('tests/StateEngine/Unit/PipelineApplicationTest.php'),
            __DIR__ . '/../tests/Unit/ForkComboTest.php' => base_path('tests/StateEngine/Unit/ForkComboTest.php'),
            __DIR__ . '/../tests/Unit/MergeComboTest.php' => base_path('tests/StateEngine/Unit/MergeComboTest.php'),
            __DIR__ . '/../tests/Unit/SplitComboTest.php' => base_path('tests/StateEngine/Unit/SplitComboTest.php'),
            __DIR__ . '/../tests/Unit/SyncComboTest.php' => base_path('tests/StateEngine/Unit/SyncComboTest.php'),
            __DIR__ . '/../tests/Unit/AllComboTest.php' => base_path('tests/StateEngine/Unit/AllComboTest.php'),
            __DIR__ . '/../tests/Unit/MultiSyncTest.php' => base_path('tests/StateEngine/Unit/MultiSyncTest.php'),
        ]);
    }

    /**
     * Register package views
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'Workflow');
    }

    /**
     * Register package routes
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
    }

    /**
     * Register package commands
     */
    protected function registerCommands(): void
    {
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
                StateEngine::class,
                DynoWorkflow::class,
               ];
    }
}