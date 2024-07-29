<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceSZState extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'SZ';
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
        $appContext->StatePath .= '->SZ';
        if ($Workflow::WorkflowDebug) {
            echo("State SZ Path: $appContext->StatePath<br/>");
        }
        //Test NULL transition

        //    $transition = $appContext->TestSEQTransitions['SZ'][$appContext->TestSEQ];
        //    $appContext->ResetTransitions($transition);
        //$appContext->ResetTransitions(0); // Null transition
        return $appContext;
    }
     */
}
