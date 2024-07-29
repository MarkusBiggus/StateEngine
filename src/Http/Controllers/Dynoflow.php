<?php

namespace MarkusBiggus\StateEngine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\DynoFactory;
use MarkusBiggus\StateEngine\Facades\DynoModel;

class Dynoflow extends Controller
{
    /**
     * Instanstiate the State Engine.
     * @return Response
     */
    public function __invoke(Request $request, string $model, string $testseq = 'testSEQ')
    {
        $modelTests = DynoFactory::new($model);

        $Workflow = DynoModel::new()->EditModel($modelTests['model']);

        $Engine = new StateEngine($Workflow);

        $appContext = $Engine->StartWorkflow($testseq);

        $testSEQ = $modelTests['testSEQ'];
        $appContext->EditTestSEQ($testSEQ);

        $Engine->RunWorkflow($appContext);

        echo($appContext->StatePath .'<br/>');
        echo('last Cycle: '. $appContext->LastCycle .'<br/>');
        return response('Workflow Complete', 200);
    }
}
