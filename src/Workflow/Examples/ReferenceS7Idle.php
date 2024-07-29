<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * S7Idle
 * demonstrate Idle state with non-standard name for state handler
 */
class ReferenceS7Idle extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S7Idle';
    /**
     * isIdle
     * used to skip a cycle, first time no transition emitted flip this switch
     * 2nd time emit transition
     * @var bool
     */
    private bool $isIdle = true;
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     *  idlestop test will never transition - Engine stops in S7Idle state after no new transitions
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $AppContext Workflow context
     *
     * @return WorkflowAppContract $AppContext
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        if ($this->isIdle || $appContext->TestSEQ == 'idlestop') {
            $appContext->StatePath .= '->S7(idle)';
            if ($Workflow::WorkflowDebug) {
                echo("State Idle S7 Path: $appContext->StatePath<br/>");
            }
            $this->isIdle = false; // transition next time
            return $appContext;
        }
        $appContext->StatePath .= '->S7';
        if ($Workflow::WorkflowDebug) {
            echo("State S7 Path: $appContext->StatePath<br/>");
        }
        $transition = $appContext->TestSEQTransitions['S7'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
     */
}
