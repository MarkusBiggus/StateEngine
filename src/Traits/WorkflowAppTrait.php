<?php

namespace MarkusBiggus\StateEngine\Traits;

/**
 * WorkflowApp contains all bespoke logic for the Workflow.
 * It extends the basic Workflow context needed to run any Workflow.
 * The app and it's State handler decide on which transitions to emit and when.
 *
 * When resumed, the Engine DispatchLastTransitions informs a State what transition made it active.
 * This can be used for 'retry' States, which are Idle states, to transition back to the State that had a retryable error,
 * like an API call, that may now work.
 */
trait WorkflowAppTrait
{
    /**
     * WorkflowDebug output
     *
     * @const bool WorkflowDebug
     */
    public const bool WorkflowDebug =  false; // true; //

    /**
     * @var  Int bitmask next Transitions from current states
     */
    protected int $TransitionsMask;

    /**
     * GetTransitions
     * Return Transitions from StateEngine that lead to current state.
     * Each Workflow State manages their transitions explicitly.
     * use: $Engine->StateTransition( $workflow->GetTransitions() ).
     *
     * @return Int $TransitionsMask
     */
    public function GetTransitions(): int
    {
        return $this->TransitionsMask;
    }

    /**
     * GetLastTransitions
     * Return Transitions from StateEngine that lead to current state.
     *  GetLastTransitionHistory format: 'stateMask' => 'transitionMask'
     *
     * @return Int $TransitionsMask
     */
    public function GetLastTransitions(): int
    {
        return reset($this->Workflow->GetLastTransitionHistory());
    }

    /**
     * GetLastTransitionNames
     * Return Transition names that lead to current state.
     *
     * @return String $names, comma separated string
     */
    public function GetLastTransitionNames(): string
    {
        return $this->Workflow->GetLastTransitionNamess();
    }

    /**
     * SetTransitions
     * Set a new Transition for current state.
     * Each Workflow State manages their transitions explicitly.
     *
     * Allows multiple transition in one cycle - like Fork?
     *
     * @param Int|String|Array $Transitions Mask|Name|array of Names
     *
     * @return Self
     */
    public function SetTransitions(int|string|array $Transitions = null): self
    {
        if ($Transitions === null) {
            return $this;
        } elseif (! is_int($Transitions)) {
            $this->TransitionsMask |= $this->Workflow->TransitionNameToMask($Transitions);
        } else {
            $this->TransitionsMask |= $Transitions;
        }
        return $this;
    }

    /**
     * ResetTransitions
     * Reset all Transitions for current state.
     * Each Workflow State manages their own transitions explicitly.
     *
     * @param Int|String|Array $Transitions Mask|Name|array of Names
     *
     * @return Self
     */
    public function ResetTransitions(int|string|array $Transitions = null): self
    {
        if ($Transitions === null) {
            $this->TransitionsMask = 0;
        } elseif (! is_int($Transitions)) {
            $this->TransitionsMask = $this->Workflow->TransitionNameToMask($Transitions);
        } else {
            $this->TransitionsMask = $Transitions;
        }
        return $this;
    }

    /**
     * GetDispatchStatus
     * Current Dispatch Status is what states will execute next dispatch cycle.
     * Usually called after the Engine stops to get final states, either Idle or Terminal.
     *
     * @return Int  Workflow states bitmask
     */
    public function GetDispatchStatus(): int
    {
        return $this->Workflow->GetDispatchStatus();
    }

    /**
     * GetWorkflowState
     *
     * Get the Last transition mask from Engine set by most recent Dispatch Cycle
     *
     * @return Array of bitmasks ['states' => StateMask, 'transitions' => TransistionMask]
     */
    public function GetWorkflowState(): array
    {
        return $this->Workflow->GetWorkflowState();
    }
}
