<?php

namespace MarkusBiggus\StateEngine\Contracts;

/**
 * The $WorkflowApp context passed into StateEngine.
 */
interface WorkflowAppContract
{
    /**
     * GetTransitions
     * Return Transitions for current state.
     * Each Workflow State must managed their transitions explicitly,
     *  unless running as a pipeline.
     * use: $Engine->StateTransition( $workflow->GetTransitions() ).
     *
     * @return int $TransitionsMask
     */
    public function GetTransitions(): int;

    /**
     * GetLastTransitions
     * Return Transitions from StateEngine that lead to current state.
     *  GetLastTransitionHistory format: 'stateMask' => 'transitionMask'
     *
     * @return Int $TransitionsMask
     */
    public function GetLastTransitions(): int;

    /**
     * GetLastTransitionNames
     * Return Transition names that lead to current state.
     *
     * @return String $names, comma separated string
     */
    public function GetLastTransitionNames(): string;

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
    public function SetTransitions(int|string|array $Transitions): self;

    /**
     * ResetTransitions
     * Reset all Transitions for current state.
     * Each Workflow State manages their own transitions explicitly.
     *
     * @param Int|String|Array $Transitions Mask|Name|array of Names
     *
     * @return Self
     */
    public function ResetTransitions(int|string|array $Transitions): self;

    /**
     * GetDispatchStatus
     * Current Dispatch Status is what states will execute next dispatch cycle.
     * Usually called after the Engine stops to get final states, either Idle or Terminal.
     *
     * @return Int  Workflow states bitmask
     */
    public function GetDispatchStatus(): int;

    /**
     * GetWorkflowState
     *  All transitions that lead to Engine stop (All Idle or Terminal)
     *  available to State procedures when Workflow resumed.
     *
     * Transitions from previous cycle available to State Handlers
     *  so they can make context based decisions how to proceed.
     *
     * @return Array of bitmasks ['states' => StateMask, 'transitions' => TransistionMask]
     */
    public function GetWorkflowState(): array;

    /**
     * InitAppContext
     * Initialise the context object for this workflow - whatever you need it to be
     *
     * Called by Workflow->CreateAppContext
     *
     * @param Array $params for app context
     *
     * @return WorkflowAppContract $appContext or null to abandon this workflow before starting
     */
    public function InitAppContext(array $params): ?WorkflowAppContract;
}
