<?php

namespace MarkusBiggus\StateEngine\Http\Controllers;

/**
 * MIT License
 *
 * Copyright (c) 2024 Mark Charles
 */
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use MarkusBiggus\StateEngine\Workflow\StateEngine as Automata;

/**
 * StateEngine Controller
 * Used by tests to run different paths through
 *  the reference model to demonstrate the various transition types.
 * Transitions are called in each State handler to emulate a Workflow that
 * makes transition descions in each State.
 * The Reference App is a Workflow emulator.
 */
class StateEngine extends Controller
{
    /**
     * Instantiate the State Engine.
     *
     * run test
     *
     * @param Request $request
     * @param String $model name of the workflow
     * @param String $testseq test sequence to run
     *
     * @return Response
     */
    public function __invoke(Request $request, string $model = null, string $testseq = null)
    {
        $currentRoute = Route::currentRouteName();
        if ($currentRoute == 'workflow.engine' && $model == null) {
            return response('State Engine', 200);
        }

        $namespace = get_class($this);
        $modelNamespace = Str::beforeLast($namespace, '\\Http');
        $modelClass = $modelNamespace.'\\Workflow\\Examples\\'.$model.'Workflow';

        $Engine = Automata::Workflow($modelClass); // throws exception if name invalid
        if ($testseq == null) {
            return response("Workflow $model", 200);
        }

        $appContext = $Engine->StartWorkflow(Str::lower($testseq));

        $appContext = $Engine->RunWorkflow($appContext);

        return response($appContext->StatePath, 200);
    }
}
