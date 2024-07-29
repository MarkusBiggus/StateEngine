<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class IdleWaitS3aState implements WorkflowStateContract
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
        $appContext->StatePath .= '->S3a';
        if ($Workflow::WorkflowDebug) {
            echo("S3a Path: $appContext->StatePath<br/>");
        }

        $transition = $appContext->TestSEQTransitions['S3'][$appContext->TestSEQ];
        $appContext->ResetTransitions($transition);

        return $appContext;
    }
}
