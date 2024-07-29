<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Workflow;
use MarkusBiggus\StateEngine\Workflow\WorkflowAppFactory;

class PipelineWorkflow extends Workflow implements WorkflowContract
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
    'Workflow' => 'Pipeline', // prefix on this class name
    /**
     * Only one start state, StateEngine will set this state to first in StateTransitions
     * when not specified
     */
    //'StartState' => 'P1',

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
    'TerminalState' => 'P7',

    // Base transitions from each origin state to target state
    'StateTransitions' => [
        'P1' => [
            ['Transition' => 'Pipe', 'TargetStates' => ['P2']],
        ],
        'P2' => [
            ['Transition' => 'T2_3_4', 'TargetStates' => ['P3','P4']],
        ],
        'P3' => [
            ['Transition' => 'Pipe', 'TargetStates' => ['P5']],
        ],
        'P4' => [
            ['Transition' => 'T4_6', 'TargetStates' => ['P6']],
        ],
        'P5' => [
            ['Transition' => 'T5_6', 'TargetStates' => ['P6']],
        ],
        'P6' => [
            ['Transition' => 'Pipe', 'TargetStates' => ['P7']],
        ],
        // 'P1' => [
        //     ['Transition' => 'T1_2', 'TargetStates' => ['P2']],
        // ],
        // 'P2' => [
        //     ['Transition' => 'T2_3_4', 'TargetStates' => ['P3','P4']],
        // ],
        // 'P3' => [
        //     ['Transition' => 'T3_5', 'TargetStates' => ['P5']],
        // ],
        // 'P4' => [
        //     ['Transition' => 'T4_6', 'TargetStates' => ['P6']],
        // ],
        // 'P5' => [
        //     ['Transition' => 'T5_6', 'TargetStates' => ['P6']],
        // ],
        // 'P6' => [
        //     ['Transition' => 'T6_7', 'TargetStates' => ['P7']],
        // ],
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
            ['OriginState' => 'P2', 'Transition' => ['T2_3_4']],
        ],
    //        ['OriginState' => 'S1', 'Transition' => ['T1_3ab' => ['S3a', 'S3b']]],
    // 'Forks' => [
    //         ['OriginState' => 'Sx', 'Transitions' => ['Tx','Ty']],
    //      ],
    /**
     * Hints for Sync tell the Engine to expect
     *  multipe transitions to occur before new State is active.
     * Transition to single state from multiple events
     *  from multiple states.
     * All transitions must be emitted for transition to occur.
     * Sync transition names must be unique
     */
    'Syncs' => [
            ['TargetState' => 'P6', 'Transitions' => ['T5_6', 'T4_6']],
        ],
    /**
     * Hints for Merge tell the Engine to expect
     *  single transition from multiple states to one state.
     * Each origin state must emit the same transition for the target state
     *  to be executed.
     */
    //'Merges' => [
    //        ['TargetState' => 'Sx', 'Transition' => ['Txx']]
    //    ],
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

        $appContext = WorkflowAppFactory::Make($this)->InitAppContext($params) ;

        // Call other App init procedures if necessary (created in this object)

        /**
         * Model has two paths to Terminal state of differing lengths,
         *  the two paths arrive at state P6 asynchronously.
         * All tests are expected to reach the terminal state without error.
         *
         * Test 'pipe1' has transition from state P4, synched with state P5 transition
         *  to P6.
         * In tests pipe1 & pipe3 state P6 runs only once, they are equivalent.
         * test pipe2 runs state P6 twice because path lengths are different and not synched,
         *  P6 runs once for each path is is on.
         *
         * The model is modified slightly for other tests:
         * Test 'pipe2' has transitions from P4 & P5 not synched
         * Test 'pipe3' has no transition from state P4 (therefore, no sync)
         *
         */
        if ($params[0] == 'pipe2') {
            $Engine->AlterWorkflow(['Delete' => ['SyncOrigin' => ['P4','T4_6']]]);
        } elseif ($params[0] == 'pipe3') {
            $Engine->AlterWorkflow(['Delete' => ['SyncOrigin' => ['P4','T4_6']]]);
            $Engine->AlterWorkflow(['Delete' => ['StateTransition' => ['P4','T4_6']]]);
        }

        return $appContext ;
    }
}
