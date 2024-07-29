<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Workflow;
use MarkusBiggus\StateEngine\Workflow\WorkflowAppFactory;

class SyncComboWorkflow extends Workflow implements WorkflowContract
{
    /**
     * WorkflowDebug output
     *
     * @const WorkflowDebug
     */
    public const bool WorkflowDebug = false; // true; //
    /*
     * StateModel of this Workflow
     *
     * @var Array $StateModel
     */
    public array $StateModel = [
        'Workflow' => 'SyncCombo',
        'StatePrefix' => 'Combo', // prefix on this class name
    /**
     * Only one start state, StateEngine will set this state to first in StateTransitions
     * when not specified
     */
    //'StartState' => 'S1',

    /**
     * Idle hint tells the Engine that these States may not emit a transition and
     *   it is OK for the Engine to stop with only Idle States set in order to pause the Workflow.
     * A paused Workflow is resumed after external conditions have changed to allow the Workflow to continue.
     *
     * Once active, Idle States are executed each Dispatch cycle until they emit a transition.
     * The Idle state handler must be prepared to run repeatedly to check if a transition should be emitted.
     * This allows an alternate thread in the Workflow to alter conditions that will allow the Idle state to proceed.
     */
    // 'Idle' => [
    //     'States' => '', // emtpy string for no states, multiple states comma separated
    // ],

    /**
     * Terminal state hint tells the Engine the Workflow is finished.
     *  No transition From terminal state.
     */
    'TerminalState' => 'S3',

    // Base transitions from each origin state to target state
    'StateTransitions' => [
        'S1' => [
            ['Transition' => 'T1_2_3_6', 'TargetStates' => ['S2','S3','S6']],
        ],
        'S2' => [
            ['Transition' => 'T2_3', 'TargetStates' => ['S3']],
            ['Transition' => 'T2_4', 'TargetStates' => ['S4']],
        ],
        'S3' => [
        ],
        'S4' => [
            ['Transition' => 'T4_5', 'TargetStates' => ['S5']],
        ],
        'S5' => [
            ['Transition' => 'T5_6_3', 'TargetStates' => ['S3']],
        ],
        'S6' => [
            ['Transition' => 'T5_6_3', 'TargetStates' => ['S3']],
        ],
    ],
    /*
    Process goes from one OriginState to multiple TargetStates either by a single transition (Split) or multiple transitions (Fork).
    Process is in multiple states with the OriginState persisting until all Fork transitions complete. No further transitions are possible whilst OrginState persists.

    Multiple OriginStates transition to one TargetState either by a single transition (Merge) or multiple transitions (Synch).
    Process is in multiple states with OriginStates persisting until all Synch transitions complete. No further transitions are possible whilst any OrginState persists.

    Transitions are counted, maximum allowable is the count of TargetStates for each Transition entry in Transitions table
     times 3 (a somewhat arbitrary limit)
    */
    // Transition to multiple target states from one event
    // Split will be inferred when not defined
    'Splits' => [
            ['OriginState' => 'S1', 'Transition' => ['T1_2_3_6']],
        ],
    'Forks' => [
            ['OriginState' => 'S2', 'Transitions' => ['T2_3','T2_4']],
        ],
    /**
     * Hints for Sync tell the Engine to expect
     *  multipe transitions to occur before new State is active.
     * Transition to single state from multiple events
     *  from multiple states.
     * All transitions must be emitted for the target state
     *  to be executed.
     */
    'Syncs' => [
            ['TargetState' => 'S3', 'Transitions' => ['T1_2_3_6', 'T2_3', 'T5_6_3']], // Sync: Split, Fork, Merge
        ],
    /**
     * Hints for Merge tell the Engine to expect
     *  single transition from multiple states to one state.
     * Each origin state must emit the same transition for the target state
     *  to be executed.
     */
    'Merges' => [
           ['TargetState' => 'S3', 'Transition' => ['T5_6_3']],
        ],
    /**
     * Parameters that affect how the Engine runs this workflow.
     * completely optional, these are the defaults used by the Engine.
     *
     *  StallCycles - how many cycles with no transitions or executed
     *      states before Workflow is considered stalled and aborts.
     *  MaxTransitionFactor - to prevent run away loops, max number of dispatch cycles limited to
     *      total number of transitions in Workflow model multipled by MaxTransitionFactor.
     */
    // 'Parameters' => [
    //         'StallCycles' => 2,
    //         'MaxTransitionFactor' => 3,
    //     ],
    ];

    /**
     * CreateAppContext
     * Create the context for this workflow (has all the Workflow bespoke logic & non-state procedures)
     *
     * Called by Engine->StartWorkflow
     *
     * @param StateEngine $Engine to run this workflow
     * @param Array $params for app context, etc
     *
     * @return WorkflowAppContract $appContext or null when workflow is to be abandoned before running
     */
    public function CreateAppContext(StateEngine $Engine, array $params): ?WorkflowAppContract
    {
        if (self::WorkflowDebug) {
            echo "<strong>CreateAppContext</strong> ".$this->GetName()." App<br/>";
        }

        // Call other App init procedures if necessary (created in this object)

        $appContext = WorkflowAppFactory::Make($this)->InitAppContext($params) ;

        return $appContext ;
    }
}
