<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceS3State extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S3';
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $AppContext Workflow context
     *
     * @return WorkflowAppContract $AppContext
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->S3';
        if ($Workflow::WorkflowDebug) {
            echo("State S3 Path: $appContext->StatePath<br/>");
        }

        $transition = $appContext->TestSEQTransitions['S3'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
     */
}
