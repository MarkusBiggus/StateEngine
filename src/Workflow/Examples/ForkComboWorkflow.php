<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Workflow;
use MarkusBiggus\StateEngine\Workflow\WorkflowAppFactory;

class ForkComboWorkflow extends Workflow implements WorkflowContract
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
        'Workflow' => 'ForkCombo',
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
    'TerminalState' => 'S6',

    // Base transitions from each origin state to target state
    'StateTransitions' => [
        'S1' => [
            ['Transition' => 'T1_5_2', 'TargetStates' => ['S2']],
            ['Transition' => 'T1_3_4', 'TargetStates' => ['S3','S4']],
            ['Transition' => 'T1_6',   'TargetStates' => ['S6']],
        ],
        'S2' => [
        ],
        'S3' => [
            ['Transition' => 'T3_5', 'TargetStates' => ['S5']],
        ],
        'S4' => [
        ],
        'S5' => [
            ['Transition' => 'T1_5_2', 'TargetStates' => ['S2']],
            ['Transition' => 'T5_6', 'TargetStates' => ['S6']],
        ],
        'S6' => [
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
            ['OriginState' => 'S1', 'Transition' => ['T1_3_4']],
        ],
    'Forks' => [
            ['OriginState' => 'S1', 'Transitions' => ['T1_5_2','T1_3_4','T1_6']],
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
            ['TargetState' => 'S6', 'Transitions' => ['T5_6', 'T1_6']],
        ],
    /**
     * Hints for Merge tell the Engine to expect
     *  single transition from multiple states to one state.
     * Each origin state must emit the same transition for the target state
     *  to be executed.
     */
    'Merges' => [
           ['TargetState' => 'S2', 'Transition' => ['T1_5_2']],
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
        /**
         * Test exceptions for missing transitions in Sync or Merge
         * Trigger too few transitions for Sync
         * Trigger too few Origin states for Merge
         */
        if ($params[0] == 'syncFail') {
            $Engine->AlterWorkflow(['Replace' => ['SyncTransitions' => ['TargetState' => 'S6', 'Transitions' => ['T5_6']]]]);
        } elseif ($params[0] == 'mergeFail') {
            $Engine->AlterWorkflow(['Delete' => ['StateTransition' => ['S5','T1_5_2']]]);
        }

        $appContext = WorkflowAppFactory::Make($this)->InitAppContext($params) ;

        return $appContext ;
    }
}
