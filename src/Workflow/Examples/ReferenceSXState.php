<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;

class ReferenceSXState extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'SX';
}
