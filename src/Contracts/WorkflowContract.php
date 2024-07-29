<?php

namespace MarkusBiggus\StateEngine\Contracts;

use MarkusBiggus\StateEngine\Workflow\StateEngine;

use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * Workflow Contract
 * Mostly implemented with WorkflowTrait
 */
interface WorkflowContract
{
    /**
     * RunState
     * Each State called by Engine to run as required
     *
     * @param StateEngine $Engine
     * @param WorkflowAppContract $appContext is current app context for this Engine
     * @param String $StateName is state to run (handler name in Model['States'] when specified, otherwsise class is 'State' appended to state name )
     * @param Bool $Singleton when False State is not kept - default state is Singleton
     *
     * @return WorkflowAppContract
     */
    public function RunState(StateEngine $Engine, WorkflowAppContract $appContext, String $StateName, Bool $Singleton = true): WorkflowAppContract;

    /**
     * SetEngine
     * Called by Engine constructor since the Workflow is instantiated first.
     * This is essential so the controller can pass the real namespace of the Workflow
     * into the Engine for all related classes to be instantiated in the App's namespace.
     *
     * @param StateEngine $Engine
     *
     * @return Self
     */
    public function SetEngine(StateEngine $Engine): self;

    /**
     * GetName
     *
     * @return String $StateModel['Workflow'] name
     */
    public function GetName(): string;

    /*
     * GetVersionBuild
     *
     * @return Array [WFVersion,WFBuild]
     */
    public static function GetVersionBuild(): array;

    /**
     * SetLogger
     *
     * @return self
     */
    public function SetLogger(WorkflowLoggerContract $WorkflowLogger): self;

    /**
     * isResumed
     * Use by Idle states to determine if they are being resumed so need to do work
     *
     * @return Bool true when Workflow was resumed
     */
    public function isresumed(): bool;

    /**
     * isDebugging
     * Should Debug info be logged by Workflow states
     *
     * @return Bool Workflow debug status
     */
    public static function isDebugging(): bool;

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
     * GetLastTransitionHistory
     *
     * Get the Last Dispatch Cycle state transitions from Engine
     * TransitionHistory has one entry per State that emitted a transition
     *
     * @return Array  format: 'stateMask' => 'transitionMask'
     */
    public function GetLastTransitionHistory(): array;

    /**
     * GetTransitionHistory
     *
     * Get current Dispatch Cycle state transitions from Engine
     * TransitionHistory has one entry per State that emitted a transition
     * Return only the state that matches the param, when supplied.
     *
     * @param String $OriginStates
     *
     * @return Array  format: [OriginMask => TransitionMask, 'DispatchCycle' => int]
     */
    public function GetTransitionHistory(string $OriginState = ''): array;

    /**
     * GetTransitionNames
     * Return Transitions for current state.
     * Each Workflow State manages their transitions explicitly, when not
     * running as a pipeline.
     *
     * @param Int $TransitionsMask
     *
     * @return Array $names
     */
    public function GetTransitionNames(int $TransitionsMask): array;

    /**
     * GetLastTransitionNames
     * Return Transition names that lead to current state.
     *
     * @return String $names, comma separated string
     */
    public function GetLastTransitionNames(): string;

    /**
     * TransitionNameToMask
     * return a bitmask that represent the names provided
     *
     * @param String|Array $Transitions Mask|Name|array of Names or comma separated list
     *
     * @return Int $TransitionsMask
     */
    public function TransitionNameToMask(string|array $Transitions): int;

    /**
     * MatchesLastTransition
     * Match the transitions supplied to the last transition from the Engine.
     * True when all supplied transitions are present in LastTransitions (maybe others, too)
     *
     * @param  Int|String|Array $transition - bitmask of zero returns false
     *
     * @return Bool true for non-exact match
     */
    public function MatchesLastTransition(int|string|array $matchTransition): bool;

    /**
     * MatchesLastTransitionExact
     * Match the transitions supplied to the last transition from the Engine.
     * True when supplied transitions are all & only present in LastTransitions
     *
     * @param  Int|String|Array $transition - bitmask of zero returns false
     *
     * @return Bool true for exact match
     */
    public function MatchesLastTransitionExact(int|string|array $matchTransition): bool;
}
