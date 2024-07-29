<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use Illuminate\Support\Arr;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;


abstract class ExmplState implements WorkflowStateContract
{
    /**
     * $thisState
     * Must match State class unique name part
     * eg. PrefixS0State, otherwise State 'handler' name must be specified for the State
     *
     * @var String
     */
    protected $thisState = 'S0'; // placeholder
    /**
     * $terminalState
     * Set true in Terminal state only
     *
     * @var Bool
     */
    protected $terminalState = false; // only 1 state needs this
    /**
     * Visit
     * used to transition differently on each execution of this state
     * Index exception will result when state executes more often than
     * anticipated by test sequence transitions.
     *
     * @var Int
     */
    private int $Visit = 0;

    /**
     * run
     * Perform State processing
     * Emit the transition when specified for this test
     * otherwise, relies on the default single transition
     *  defined in the model to next State(s),
     * or Null transition ends this path through the model.
     * When an array of Transitions is provided, transitions are emitted
     * one per execution of the state. The State must be singleton (default)
     * to work as intended.
     * When TestSEQTransitions has a value for any State, each test that state
     * participates must have a test entry, even if null.
     * When TestSEQTransitions has no value for a State, null transition (or default transition)
     * will be emitted each execution.
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

        if ($this->terminalState) {
            $appContext->LastCycle = $Workflow->GetTransitionHistory()['DispatchCycle'];
        }
        if (! isset($appContext->TestSEQTransitions[$this->thisState])) {
            return $appContext;
        }
        if (Arr::exists($appContext->TestSEQTransitions[$this->thisState],$appContext->TestSEQ)) {
            if (is_array($appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ])) {
                $transition = $appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ][$this->Visit++];
            } else {
                $transition = $appContext->TestSEQTransitions[$this->thisState][$appContext->TestSEQ];
            }
            $appContext->ResetTransitions($transition);
        } else if (!$this->terminalState) {
            throw new \RuntimeException("TestSEQ error: '$appContext->TestSEQ' not a test sequence for $this->thisState !");
        }
        return $appContext;
    }
}