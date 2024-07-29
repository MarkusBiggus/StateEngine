<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

/**
 * MIT License
 *
 * Copyright (c) 2024 Mark Charles
 */
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;

use MarkusBiggus\StateEngine\Workflow\Workflow;

class ReferenceWorkflow extends Workflow implements WorkflowContract
{
    /**
     * WorkflowDebug output
     *
     * @const WorkflowDebug
     */
    public const bool WorkflowDebug =  false; // true; //
    /*
     * StateModel of this Workflow
     *
     * @var Array $StateModel
     */
    public array $StateModel = [
    'Workflow' => 'Reference',
//    'StatePrefix' => 'Reference', // prefix of class names (required when diff to Workflow name)
    // 'StateName' => [Index, Mask, Handler] (all optional)
    'States' => [
        'S1' => [
            'handler' => 'S1x', // Use class name not ending in 'State'
        ],
        'S2' => [
        ],
        'S3' => [
            'prototype' => true, // Not a singleton, fresh State each execution
        ],
        'S4' => [
            'prototype' => false, // default is singleton, not prototype
        ],
        'S5' => [
        ],
        'S6' => [
            'prototype' => 'false', // default is singleton
        ],
        'S7' => [
            'handler' => 'ReferenceS7Idle', // Use class not ending in 'State'
        ],
    //    'SX' => [ // no transition - target state - is inferred
    //    ],
    //    'SY' => [ // no transition - target state - is inferred
    //    ],
        'SZ' => [
            'prototype' => 'true', // Not a singleton, fresh State each execution
        ],
        'S8' => [
        ],
    ],

    /**
     * Only one Start state.
     *  StateEngine will do an implicit transition to this state when Workflow starts
     */
    'StartState' => 'S1',

    /**
     * Idle hint tells the Engine that these States may not emit a transition and
     *   it is OK for the Engine to stop with only Idle States set in order to pause the Workflow.
     * A paused Workflow is resumed after external conditions have changed to allow the Workflow to continue.
     *
     * Once active, Idle States are executed each Dispatch cycle until they emit a transition.
     * The Idle state handler must be prepared to run repeatedly to check if a transition should be emitted.
     * This allows an alternate thread in the Workflow to alter conditions that will allow the Idle state to proceed.
     * A single transition will not be enabled for 'autoTransition' from an Idle state.
     *
     * Limit of two dispatch cycles with no new transitions will stop workflow in Idle States.
     * Engine restarts later and reprocesses Idle states in anticipation external factors will now permit transition.
     * (require Logger to save/resume orkflow)
     */
    'Idle' => [
        'States' => 'S4,S7', // emtpy string for no Idle states, multiple states comma separated
    //    'Handoff' => [ // handoff happens in lieu of transition from Idle state, picked up by Receiver in Idle state
    //        ['State'=> ReferenceStateMask::S7, 'Receiver' => [['Silo:ICT', 'Role:Mgr'],['Silo:HR', 'Role:Admin']]],  // Evaluated by Gates
    //        ['State'=> ReferenceStateMask::S7, 'Receiver' => ['Silo:ICT', 'Role:Mgr']],  // Evaluated by Gates
    //    ],
    ],

    /**
     * Terminal state hint tells the Engine the Workflow is finished.
     *  No transition From terminal state.
     */
    'TerminalState' => 'S8',

    /**
     * Main part of the model - transitions between states
     * Base transitions from each origin state to target state.
     *
     * This is where all Transitions must be defined.
     * Except for StartState, every state must have a transition to it.
     * Not every state must transition,
     *  fork/split paths may have terminal paths (that does not imply a terminal state on those paths)
     *  no transition will end a path, when no autoTransition defined.
     * A single transition will be emitted by default, 'autoTransition' is set on single non-idle state transitions, as in pipeline behaviour.
     *  To override, 'autoTransition' can  be specified to stop a default transition being set.
     * Transition to self can not have 'autoTransition', must be emitted so no transition can end the loop.
     */
    'StateTransitions' => [
            'S1' => [
                ['Transition' => 'T1_2', 'TargetStates' => ['S2']],
                ['Transition' => 'T1_8a', 'TargetStates' => ['S8']],
                ['Transition' => 'T1_8b', 'autoTransition' => true, 'TargetStates' => ['S8']],  // default transition not allowed - don't do this, will be ignored
                ['Transition' => 'T1_X', 'TargetStates' => ['SX']],
            ],
            'S2' => [
                ['Transition' => 'T2_3', 'TargetStates' => ['S3']],
                ['Transition' => 'T2_4', 'TargetStates' => ['S4']],
                ['Transition' => 'T2_3_4', 'TargetStates' => ['S4','S3']],
                ['Transition' => 'T2_SplitMerge', 'TargetStates' => ['SY','S7']],
                ['Transition' => 'T2_5', 'TargetStates' => ['S5']],
                ['Transition' => 'T2_6', 'TargetStates' => ['S6']],
                ['Transition' => 'T2_Z', 'TargetStates' => ['SZ']],
            ],
            'S3' => [
                ['Transition' => 'T3_8', 'autoTransition' => false, 'TargetStates' => ['S8']], // disable default, require Transition to be explicitly emitted by state
            ],
            'S4' => [
                ['Transition' => 'T4_8', 'autoTransition' => true, 'TargetStates' => ['S8']], // default transition not allowed on Idle state - don't do this, will be ignored
            ],
            'S5' => [
                ['Transition' => 'T2_SplitMerge', 'TargetStates' => ['S7']],  // non-Idle states have autoTransition set for single Transition
            ],
            'S6' => [
                ['Transition' => 'T6_7', 'TargetStates' => ['S7']],
            ],
            'S7' => [
                ['Transition' => 'T7_8', 'TargetStates' => ['S8']], // Idle - no autoTransition
            ],
            'SZ' => [
                ['Transition' => 'TZ_Y_Z', 'TargetStates' => ['SY','SZ']], // can't autoTransition to self
            ],
        ],
    /*
     * Process goes from one OriginState to multiple TargetStates either by a single transition (Split) or multiple transitions (Fork).
     * Process is in multiple states with the OriginState persisting until all Fork transitions complete. No further transitions are possible whilst OrginState persists.
     *
     * Multiple OriginStates transition to one TargetState either by a single transition (Merge) or multiple transitions (Synch).
     * Process is in multiple states with OriginStates persisting until all Synch transitions complete. No further transitions are possible whilst any OrginState persists.
     */

    /**
     * Transition to multiple target states from single transition
     */
    'Splits' => [
            ['OriginState' => 'S2', 'Transition' => ['T2_3_4']],
            ['OriginState' => 'S2', 'Transition' => ['T2_SplitMerge']],
        //    ['OriginState' => 'SZ', 'Transition' => ['TZ_Y_Z']], // Inferred during model refactor
        ],
    /**
     * Transition to multiple target states from multiple events (in origin state until all transitions done, no further transitions while origin state persists)
     * Fork events maybe Transitioned individually or ALL at once.
     * Origin states are executed each cycle until all transitions are emitted.
     * Limit of two dispatch cycles with no new transitions will abort a stalled workflow.
     */
    'Forks' => [
            ['OriginState' => 'S1', 'Transitions' => ['T1_8b', 'T1_X']],
            ['OriginState' => 'S2', 'Transitions' => ['T2_SplitMerge', 'T2_Z']],
            ['OriginState' => 'S2', 'Transitions' => ['T2_3', 'T2_4']],
        ],
    /**
     * Hints for Synch tell the Engine to expect
     *  multipe transitions to occur before new State is active.
     * Transition to single state from multiple events
     *  from multiple states. All transitions must be emitted for tranition to occur.
     */
    'Syncs' => [
            ['TargetState' => 'S7', 'Transitions' => ['T2_SplitMerge', 'T6_7']],
            ['TargetState' => 'S8', 'Transitions' => ['T1_8a', 'T1_8b']],
            ['TargetState' => 'S8', 'Transitions' => ['T3_8', 'T4_8']],
        ],
    /**
     * Hints for Merge tell the Engine to expect
     *  single transition from multiple states to one state.
     * Each origin state must emit the same transition for the target state
     *  to be executed.
     */
    'Merges' => [
            ['TargetState' => 'S7', 'Transition' => ['T2_SplitMerge']],
        ],
    /**
     * Parameters that affect how the Engine runs this workflow.
     * completely optional, defaults are used by the Engine.
     *
     *  StallCycles - how many cycles with no transitions or executed
     *      states before Workflow is considered stalled and aborts.
     *  MaxTransitionFactor - to prevent run away loops, max number of dispatch cycles limited to
     *      total number of transitions in Workflow model multipled by MaxTransitionFactor.
     *      Default is zero to allow loops (unlimited transitions).
     */
    'Parameters' => [
            'StallCycles' => 2,
            'MaxTransitionFactor' => 3,
        ],
    ];
}
