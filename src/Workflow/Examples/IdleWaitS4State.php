<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * S4
 * demonstrate Idle states with same transition for Rety
 */
class IdleWaitS4State implements WorkflowStateContract
{
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->S4';
        if ($Workflow::WorkflowDebug) {
            echo("S4 Path: $appContext->StatePath<br/>");
        }
        $transition = $appContext->TestSEQTransitions['S4'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
}
