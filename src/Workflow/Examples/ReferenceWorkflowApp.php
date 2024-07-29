<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;
use MarkusBiggus\StateEngine\Workflow\WorkflowApp;

/**
 * The $appContext passed into the Engine
 * Variable in this object are the context of the App using this StateModel.
 *
 * It is possible to have any number of App variants using the same StateModel.
 * For testing, there is a Controller variant for each of the StateModel transition types being tested.
 */
class ReferenceWorkflowApp extends WorkflowApp implements WorkflowAppContract
{
    /**
     * StatePath
     *
     * @var String
     */
    public $StatePath ; // TEST only - string of States visited
    /**
     * TestSEQ
     *
     * @var String
     */
    public $TestSEQ ; // TEST only - which sequence testing
    /**
     * TestSEQTransitions
     *
     * @var Array
     */
    public $TestSEQTransitions ; // TEST only - State find its transition

    /**
     * StateEngine TEST sequence SEQ18
     *
     * Step through reference model S1->S8
     * Single transition <T1-8a>
     *
     * The DispatchStatus of a State is how that state was established, the transition that lead to it.
     * For transition S1-S8 either of two transition can be emitted by S1.
     *  S8 can use the DispatchStatus to see which transition was emitted,
     *  giving S8 some insight to the prevailing conditions in S2 and behave accordingly.
     *
     * StateEngine TEST sequence SplitSyncSEQ
     *
     * Split: Multiple states active from single transition
     * Step through reference model SPLIT/SYNC S1->S2->S3S4->S8

     * States that are split run independently, one without the other.
     * S2->S3S4 when S2 is cleared and both S3 & S4 become executable.
     * eg. S3 & S4 both execute in the same DispatchCycle (in order of their StateModel index)
     *
     * StateEngine TEST sequence ForkSyncSEQ
     *
     * Fork: Multiple states active from multiple transitions by single OriginState
     * Step through reference model FORK/SYNC S1->S2->S3S4->S8
     * Transitions <T1-2> <T2_3> <T2_4> <T3_8> <T4_8>
     *
     * States that are forked can not be run independently, one without the other.
     * S2->S3 will wait for S2->S4 before S2 is cleared, at which time both S3 & S4 become executable.
     * eg. Active states are: S2 S2S3 S3S4 - S3 & S4 both execute in the same DispatchCycle (in order of their StateModel index)
     * The Sync states S3->S8 & S4->S8 can never occur if S2->S4 doesn't happen first.
     * Path S2->S3->S8 will leave S2 active waiting for S2->S4 to clear the Fork S2.
     *  This will cause S8 to never be established because Sync waits for S4->S8.
     *
     *
     * StateEngine TEST sequence ForkMergeSEQ
     *
     * Merge: Multiple OriginStates transition to one TargetState by same transition emitted by each OriginState
     * S7 does not emit a transition until 2nd time it runs
     * Step through reference model FORK/MERGE S1->S2->S5S6->S7->S8
     * Transitions <T1-2> <T2_5> <T2_6> <T5_6_7> <T7_8>
     *
     * StateEngine TEST sequence SplitMergeSEQ
     *
     * Sync: Multiple OriginStates transition to one TargetState by single transition from OriginStates
     * S7 does not emit a transition until 2nd time it runs
     * Step through reference model SPLIT/MERGE S1->S2->S5S6->S7->S8
     * Transitions <T1-2> <T2_5_6> <T5_6_7> <T7_8>
     *
     * StateEngine TEST sequence IdleStopSEQ
     *
     * Split: OriginState transitions to multiple TargetStates by single transition
     * S7 does not emit a transition in this test
     * Step through reference model SPLIT/Idle S1->S2->S5S6->S7Idle
     * Transitions <T1-2> <T2_5> <T2_6> <T5_6_7>
     *
     * StateEngine TEST sequence DudState
     *
     * State emits a transition it is not an Origin state for.
     * S2 emits a transition for S5 in this test
     * Step through reference model S1->S2->bang
     * Transitions <T1-2> <T5_6_7>
     *
     * StateEngine TEST sequence Terminal
     *
     * State emits a transition it is not an Origin state for.
     * S2 emits a transition for S5 in this test
     * Step through reference model S1->S2->bang
     * Transitions <T1-2> <T5_6_7>
     */

    /**
     * InitAppContext
     * Initialise the context object for this workflow
     *
     * Called by Workflow->CreateAppContext
     *
     * @param Array $params
     *
     * @return WorkflowAppContract $appContext or null when workflow is to be abandoned before running
     */
    public function InitAppContext(array $params): ?WorkflowAppContract
    {
        if (self::WorkflowDebug) {
            echo "<strong>InitAppContext</strong> ".$this->Workflow->GetName()." App<br/>";
        }

        // TestSEQ is name for test being run
        $this->TestSEQ = $params[0];
        $this->StatePath = 'I';

        /**
         * Sequences for each test
         *
         * seq18a    : I->S1x->S1x->S1x->SX->S8
         * seq18b    : I->S1x->SX->S8
         * seq18c    : I->S1x->SX->S8
         * forksync  : I->S1x->S2->S2->S3->S4->S4->S8
         * splitsync : I->S1x->S2->S3->S4->S4->S8
         * forkmerge : I->S1x->S2->S2->S2->S5->S6->S7Idle->SZ->SY->S7Idle->S8
         * splitmerge: I->S1x->S2->S2->S2->S5->S6->S7Idle->SZ->SY->S7Idle->S8
         * idlestop  : I->S1x->S2->S3->S4->S4->S4
         * forknosync: Exception: STALLED: No ready states! DispatchCycle: 5 DispatchState: S4
         * dudstate  : Exception: Invalid Transition for state: 'S2' ! Transition: T3_8
         * terminal  : Exception: isTerminal - no transition allowed! Transition: T1_8b
         * invalid   : Exception: ABORT: Invalid transition name: T1_8 from state S1
         */

        $this->TestSEQTransitions = [
            'S1x' => [
                'seq18a' => ['T1_8a', 'T1_8b', 'T1_X'],
                'seq18b' => 'T1_8b, T1_X, T1_8a',
                'seq18c' => ['T1_8b, T1_X, T1_8a'],
                'forksync' => 'T1_2',
                'splitsync' => 'T1_2',
                'forkmerge' => 'T1_2',
                'splitmerge' => 'T1_2',
                'idlestop' => 'T1_2',
                'forknosync' => 'T1_2',
                'dudstate' => 'T1_2',
                'terminal' => 'T1_8a, T1_8b, T1_X',
                'invalid' => 'T1_8',
            ],
            'S2' => [
                'forksync' => ['T2_3', 'T2_4'],
                'splitsync' => 'T2_3_4',
                'forkmerge' => ['T2_Z', 'T2_5, T2_6', 'T2_SplitMerge'], // activate Fork 1st to prevent Workflow stall
                'splitmerge' =>  ['T2_SplitMerge', 'T2_5,T2_6', 'T2_Z'], // activate Merge 1st to prevent Workflow stall
                'idlestop' => 'T2_3, T2_4',
                'forknosync' => 'T2_3, T2_4',
                'dudstate' => 'T3_8',
                'invalid' => 'T2_8',
            ],
            'S3' => [
                'forksync' => 'T3_8',
                'splitsync' => 'T3_8',
                'forknosync' => '', // auto disabled for only transition - Exception
                'idlestop' => 'T3_8',
            ],
            'S4' => [
                'forksync' => ['', 'T4_8'], // delay
                'splitsync' => ['', 'T4_8'], // delay
                'forknosync' => ['', 'T4_8'], // delay
                'idlestop' => ['', '',''], // idle until stop - set by 'Parameters' => ['StallCycles' => tinyint]
            ],
            'S5' => [
                'forkmerge' => '', // auto transition will emit 'T2_SplitMerge'
                'splitmerge' => 'T2_SplitMerge', // doesn't have to be auto
            ],
            'S6' => [
                'forkmerge' => '', // auto transition will emit 'T6_7'
                'splitmerge' => '',
            ],
            'S7Idle' => [
                'forkmerge' => ['', 'T7_8'], // after Sync - idle once before emit
                'splitmerge' => ['', 'T7_8'],
            ],
            'SZ' => [
                'forkmerge' => null,
                'splitmerge' => null,
                'dudstate' => ['TZ_Z', 'T3_8'], // self, Exception - invalid transition for SZ
            ],
            'S8' => [
                'terminal' => 'T1_8b', // Exception - not allowed
            ],
        ];
        return $this ;
    }
}
