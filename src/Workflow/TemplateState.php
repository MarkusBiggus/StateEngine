<?php

namespace MarkusBiggus\StateEngine\Workflow;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * Template
 * Use this as a template for State classes
 * See Examples folder for use cases in Tests.
 * DualIdleS2State - emit transition conditionally
 * IdleWaitS2State - emit transition on how state was transitioned to (path based decision)
 * IdleWaitS5State - get transition names (path to the state)
 * ReferenceS7Idle - doesn't emit transition when idle
 * ReferenceSZState - doesn't emit transition - ends a multibranch path after Split or Fork
 */
abstract class TemplateState implements WorkflowStateContract
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
        if ($Workflow::WorkflowDebug) {
            echo "ExampleState: Interesting debug info <br/>";
        }

        /**
         * Bespoke app logic for this state
         */

        // $appContext = whatever app needs to do

        /**
         * Emit a transition (maybe conditional for Idle state, they are allowed to not)
         * Any number maybe emitted (according to Workflow model)
         * Transition executes after the state has returned the updated $appContext
         */
        $transition = 'T1_2'; // must set a transition defined in the model for this state
        $appContext->ResetTransitions($transition);
        return $appContext;
    }
}
