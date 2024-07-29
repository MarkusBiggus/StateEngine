<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceS4State extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S4';
    /**
     * isDelayed
     * used to create lag in model path on one branch.
     * 2nd time emit transition to avoid stalling Engine
     * @var Bool
     */
    private bool $isDelayed = true;
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
        if ($this->isDelayed) {
            $appContext->StatePath .= '->S4(delay)';
            if ($Workflow::WorkflowDebug) {
                echo("S4 Delay: $appContext->StatePath<br/>");
            }
            $this->isDelayed = false; // transition next time
            // no transition $appContext->ResetTransitions('');
            return $appContext;
        }
        $appContext->StatePath .= '->S4';
        if ($Workflow::WorkflowDebug) {
            echo("State S4 Path: $appContext->StatePath<br/>");
        }
        $transition = $appContext->TestSEQTransitions['S4'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
     */
}
