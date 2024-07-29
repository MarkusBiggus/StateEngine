<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use Illuminate\Support\Arr;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;


abstract class ExmplIdleState implements WorkflowStateContract
{
    protected $thisState = 'S0'; // placeholder
    /**
     * Visit
     * used to transition differently on each execution of this state
     *
     * @var Int
     */
    private int $Visit = 0;

    /**
     * isIdle
     * used to skip a cycle, first time no transition emitted flip this switch
     * 2nd time emit transition
     * @var Bool
     */
    //private bool $isIdle = true;

    /**
     * run
     * Perform State processing
     * Emit the transition when specified for this test
     * otherwise, relies on the default single transition
     *  defined in the model to next State(s),
     * or Null transition ends this path through the model
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
     */
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        $appContext->StatePath .= '->'.$this->thisState;

        if ($Workflow::WorkflowDebug) {
            echo($this->thisState.": $appContext->StatePath<br/>");
        }

        // if (! isset($appContext->TestSEQTransitions[$this->thisState])) {
        //     return $appContext;
        // }


        // if ($this->isIdle || $appContext->TestSEQ == 'idlestop') {
        //     $appContext->StatePath .= "->$this->thisState(idle)";
        //     if ($Workflow::WorkflowDebug) {
        //         echo("$this->thisState Idle: $appContext->StatePath<br/>");
        //     }
        //     $this->isIdle = false; // transition next time
        //     return $appContext;
        // }
        if (Arr::exists($appContext->TestSEQTransitions[$this->thisState],$appContext->TestSEQ)) {
            if (is_array($appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ])) {
                $transition = $appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ][$this->Visit++];
            } else {
                $transition = $appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ];
            }
            $appContext->ResetTransitions($transition);
        } else {
            throw new \RuntimeException("TestSEQ error: '$appContext->TestSEQ' not a test sequence for $this->thisState !");
        }
        return $appContext;
    }
}