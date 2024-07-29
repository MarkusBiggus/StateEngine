<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceS8State extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S8';
    protected $terminalState = true;
}
