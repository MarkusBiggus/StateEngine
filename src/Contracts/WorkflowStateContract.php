<?php

namespace MarkusBiggus\StateEngine\Contracts;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

interface WorkflowStateContract
{
    /**
     * run
     * Perform bespoke State processing and set Transition(s) to next State(s)
     *
     * @param WorkflowContract $Workflow current Workflow being processed
     * @param WorkflowAppContract $AppContext Workflow context
     *
     * @return WorkflowAppContract $AppContext
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $AppContext): WorkflowAppContract;
}
