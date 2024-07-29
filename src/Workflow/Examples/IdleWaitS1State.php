<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * S1
 * Start state to split to test Idle Wait states multiple cycles
 */
class IdleWaitS1State implements WorkflowStateContract
{
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * Called by Engine->dispatcher()->Workflow->RunState()
     *
     * @param WorkflowApp $WorkflowApp app context being processed
     *
     * @return WorkflowApp $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->S1';
        if ($Workflow::WorkflowDebug) {
            echo "S1 Path: $appContext->StatePath<br/>";
        }

        // Get the transition for the test being executed
        $transition = $appContext->TestSEQTransitions['S1'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);
        return $appContext;
    }
}
