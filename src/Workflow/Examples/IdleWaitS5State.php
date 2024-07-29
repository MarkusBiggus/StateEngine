<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class IdleWaitS5State implements WorkflowStateContract
{
    /**
     * run
     * Terminal State - no transition
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->S5(Terminal)';
        if ($Workflow::WorkflowDebug) {
            echo("S5 Path: $appContext->StatePath<br/>");
        }
        if ($Workflow::WorkflowDebug) {
            echo("Last Transitions: ".$Workflow->GetLastTransitionNames()."<br/>");
        }

        //No transition from terminal state

        return $appContext;
    }
}
