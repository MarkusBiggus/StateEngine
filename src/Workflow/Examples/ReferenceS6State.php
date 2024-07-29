<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceS6State extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S6';
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
        $appContext->StatePath .= '->S6';
        if ($Workflow::WorkflowDebug) {
            echo("State S6 Path: $appContext->StatePath<br/>");
        }
        $transition = $appContext->TestSEQTransitions['S6'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
     */
}
