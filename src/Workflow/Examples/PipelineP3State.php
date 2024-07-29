<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class PipelineP3State implements WorkflowStateContract
{
    /**
     * run
     * Perform State processing and relies on the single default transition
     *  defined in the model to next State(s) before returning
     *
     * Test emulates a pipeline whose states do not explicitly emit transitions.
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        if (isset($appContext->TestSEQTransitions['P3'][$appContext->TestSEQ])) {
            $transition = $appContext->TestSEQTransitions['P3'][$appContext->TestSEQ];
            $appContext->ResetTransitions($transition);
        }

        $appContext->StatePath .= '->P3';

        if ($Workflow::WorkflowDebug) {
            echo("P3: $appContext->StatePath<br/>");
        }

        return $appContext;
    }
}
