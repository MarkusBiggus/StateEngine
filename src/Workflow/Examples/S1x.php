<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * S1x
 * demonstrate non-standard name for state handler
 */
class S1x extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S1x';
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * Called by Engine->dispatcher()->Workflow->RunState($Engine, $appContext)
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $AppContext Workflow context
     *
     * @return WorkflowAppContract $AppContext
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->S1x';
        if ($Workflow::WorkflowDebug) {
            echo "State S1x Path: $appContext->StatePath<br/>";
        }

        // Get the transition for the test being executed
        $transition = $appContext->TestSEQTransitions['S1x'][$appContext->TestSEQ];

        $appContext->ResetTransitions($transition);
        return $appContext;
    }
     */
}
