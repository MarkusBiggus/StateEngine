<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;

class ReferenceSYState extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'SY';
}
