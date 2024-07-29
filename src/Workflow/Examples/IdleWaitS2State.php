<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

use MarkusBiggus\StateEngine\Enumeration\TestTransitionMask;

class IdleWaitS2State implements WorkflowStateContract
{
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * Test emulates an Idle state waiting for other states to catch up
     * Demonstrates checking transition to a State to determine next transition emitted.
     *
     * This state will transition when it is transitioned to from S4 via T4_2 transition
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        if ($Workflow->MatchesLastTransition('T4_2')) {
            $appContext->StatePath .= '->S2';
            if ($Workflow::WorkflowDebug) {
                echo("S2 Path: $appContext->StatePath<br/>");
            }
            $transition = $appContext->TestSEQTransitions['S2'][$appContext->TestSEQ];
            $appContext->ResetTransitions($transition);
            return $appContext;
        }
        if (isset($appContext->TestSEQTransitions['S2'][$appContext->TestSEQ])) {
            $transition = $appContext->TestSEQTransitions['S2'][$appContext->TestSEQ];
            $appContext->ResetTransitions($transition);
        }

        if ($Workflow::WorkflowDebug) {
            echo("S2 Idle: $appContext->StatePath<br/>");
        }
        $appContext->StatePath .= '->S2(wait)';
        $appContext->ResetTransitions('');

        return $appContext;
    }
}
