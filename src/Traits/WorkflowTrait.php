<?php

namespace MarkusBiggus\StateEngine\Traits;

use RuntimeException;

use MarkusBiggus\StateEngine\Contracts\WorkflowLoggerContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;

/**
 * Workflow contains the model and state information that drives the StateEngine.
 * The basic Workflow model is extended for each App that runs on the model.
 */
trait WorkflowTrait
{
    /**
     * TransitionMasks
     *
     * reindexed StateModel Transition keyed by Transition,
     * Value of TransitionMasks are index to Engine->MaskTransitions
     *
     * @var Array
     */
    public array $TransitionMasks = [];

    /**
     * Engine running this Workflow
     *
     * @var StateEngine
     */
    protected StateEngine $Engine;

    /**
     * isResumed true when ResumeWorkflow used to restart a workflow
     *
     * @var bool isResumed
     */
    protected bool $isResumed = false;

    /**
     * isCacheable
     * Controls when this workflow is cached or not.
     * Cached Workflow are saved to disk and not recompiled unless version number changes.
     *
     * @param Bool isCacheable sets isCacheable when supplied
     * @return Bool true when Workflow is cacheable
     */
    public function isCacheable(bool $isCacheable = null): bool
    {
        if (isset($isCacheable)) {
            $this->cacheable = $isCacheable;
        }
        return $this->cacheable;
    }

    /**
     * Optional WorkflowLogger
     *
     * @var WorkflowLoggerContract $Logger;
     */
    private WorkflowLoggerContract $Logger;

    /**
     * Handlers instantiated once only by $this->RunState()
     *
     * @var Array
     */
    private array $Handlers = [];

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
    public function SetEngine(StateEngine $Engine): self
    {
        $this->Engine = $Engine;
        return $this;
    }

    /**
     * GetName
     *
     * @return String $StateModel['Workflow'] name
     */
    public function GetName(): string
    {
        return $this->StateModel['Workflow'];
    }

    /**
     * GetVersionBuild
     *
     * @return Array [WFVersion,WFBuild]
     */
    public static function GetVersionBuild(): array
    {
        return [
            'WFVersion' => self::WFVersion,
            'WFBuild' => self::WFBuild,
        ];
    }

    /**
     * SetLogger
     *
     * @return self
     */
    public function SetLogger(WorkflowLoggerContract $WorkflowLogger): self
    {
        $this->Logger = $WorkflowLogger;

        return $this;
    }

    /**
     * isResumed
     * Use by Idle states to determine if they are being resumed so need to do work to resume app context
     *
     * @param Bool isResumed sets isResumed when supplied
     * @return Bool true when Workflow was resumed
     */
    public function isresumed(bool $isResumed = null): bool
    {
        if (isset($isResumed)) {
            $this->isResumed = $isResumed;
        }
        return $this->isResumed;
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
        return $this->Engine->GetDispatchStatus();
    }

    /**
     * GetWorkflowState
     *
     * Get the Last state transition bitmasks from Engine set by most recent Dispatch Cycle
     *
     * @return Array of bitmasks ['states' => StateMask, 'transitions' => TransistionMask]
     */
    public function GetWorkflowState(): array
    {
        return $this->Engine->GetWorkflowState();
    }

    /**
     * GetLastTransitionHistory
     *
     * Get the Last Dispatch Cycle state transitions from Engine
     * TransitionHistory has one entry per State that emitted a transition
     * Return pnly the state that matches the param, when supplied.
     *
     * @param String $OriginStates
     *
     * @return Array  format: [OriginMask => TransitionMask]
     */
    public function GetLastTransitionHistory(string $OriginState = ''): array
    {
        $OriginMask = $this->Engine->StatesMaskFromNames($OriginState);
        $LastTransitionHistory = $this->Engine->GetLastTransitionHistory();
        if ($OriginMask) {
            return $LastTransitionHistory[$OriginMask] ?? 0;
        } else {
            return $LastTransitionHistory;
        }
    }

    /**
     * GetTransitionHistory
     *
     * Get the Last Dispatch Cycle state transitions from Engine
     * TransitionHistory has one entry per State that emitted a transition
     * Return only the state that matches the param, when supplied.
     *
     * @param String $OriginStates
     *
     * @return Array  format: [OriginMask => TransitionMask, 'DispatchCycle' => int]
     */
    public function GetTransitionHistory(string $OriginState = ''): array
    {
        $OriginMask = $this->Engine->StatesMaskFromNames($OriginState);
        $TransitionHistory = $this->Engine->GetTransitionHistory($OriginMask);
        return $TransitionHistory;
    }

    /**
     * GetTransitionNames
     * Return Transitions for current state.
     * Each Workflow State manages their transitions explicitly, unless
     *  running as a pipeline.
     *
     * @param Int $TransitionsMask
     *
     * @return Array $names
     */
    public function GetTransitionNames(int $TransitionsMask): array
    {
        return $this->Engine->GetTransitionNames($TransitionsMask);
    }

    /**
     * GetLastTransitionNames
     * Return Transition names that lead to current state.
     *
     * @return String $names, comma separated string
     */
    public function GetLastTransitionNames(): string
    {
        $names = $this->GetTransitionNames($this->Engine->GetLastTransitionHistory()['transitionMask']);
        return implode(', ',$names);
    }

    /**
     * TransitionNameToMask
     * return a bitmask that represent the transition names provided
     *
     * @param String|Array $Transitions Mask|Name|array of Names or comma separated list
     *
     * @return Int $TransitionsMask
     */
    public function TransitionNameToMask(string|array $Transitions): int
    {
        return $this->Engine->TransitionNameToMask($Transitions);
    }

    /**
     * MatchesLastTransition
     * Match the transitions supplied to the last transition from the Engine.
     * True when all supplied transitions are present in LastTransitions (maybe others, too)
     *
     * @param  Int|String|Array $transition - bitmask of zero returns false
     *
     * @return Bool true for non-exact match
     */
    public function MatchesLastTransition(int|string|array $matchTransition): bool
    {
        $transitions = $this->Engine->GetWorkflowState();
        $lastTransitions = $transitions['transitions'];

        if (is_int($matchTransition)) {
            $matchTransitionMask = $matchTransition;
        } else {
            $matchTransitionMask = $this->Engine->TransitionNameToMask($matchTransition);
        }
        return (($matchTransitionMask !== 0) && ($matchTransitionMask & $lastTransitions) === $matchTransitionMask);
    }

    /**
     * MatchesLastTransitionExact
     * Match the transitions supplied to the last transition from the Engine.
     * True when supplied transitions are all & only present in LastTransitions
     *
     * @param  Int|String|Array $transition - bitmask of zero returns false
     *
     * @return Bool true for exact match
     */
    public function MatchesLastTransitionExact(int|string|array $matchTransition): bool
    {
        $transitions = $this->Engine->GetWorkflowState();
        $lastTransitions = $transitions['transitions'];

        if (is_int($matchTransition)) {
            $matchTransitionMask = $matchTransition;
        } else {
            $matchTransitionMask = $this->Engine->TransitionNameToMask($matchTransition);
        }
        return (($matchTransitionMask !== 0) && ($matchTransitionMask ^ $lastTransitions) === 0);
    }
}
