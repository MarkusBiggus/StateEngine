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
class MergeComboWorkflowApp extends WorkflowApp implements WorkflowAppContract
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

        $this->StatePath = 'I';
        // TestSEQ is name of test being run
        $this->TestSEQ = $params[0];
        /**
         * Sequences for each test
         * See CreateAppContext in the model for test modifications to trigger exceptions
         *
         * Merge transitions are emitted asychrly to be synced
         *
         * merge  : I->S1->S2->S3->S4->S5->S3->S3a->S5->S3->S6
         * merge0 : I->S1->S2->S3->S4->S5->S3->S4->S3->S3a->S3->S3->S6
         * merge1 : I->S1->S2->S3->S4->S5->S3->S3a->S4->S3a->S6
         */
        $this->TestSEQTransitions = [
            'S1' => [
                'merge'   => 'T1_2_3_4_5',
                'merge0'   => 'T1_2_3_4_5',
                'merge1'   => 'T1_2_3_4_5',
            ],
            'S2' => [
                'merge'    => 'T2_3_6',
                'merge0'   => 'T2_3_6',
                'merge1'   => 'T2_3_6',
            ],
            'S3' => [
                'merge'   => ['T3_3a', '', 'T2_3_6'], // skip a cycle so Fork in progress and rerun S3 origin
                'merge0'   => ['', 'T3_3a', '', '', 'T2_3_6'], // extra null transition almost stalls but transitions at last chance
                'merge1'   => ['T3_3a', 'T2_3_6'],
            ],
            'S3a' => [
                'merge'    => 'T2_3_6', // do Merge, still in progress and rerun S3 origin to emit its Merge transition
                'merge0'   => 'T2_3_6', // do Merge, still in progress and rerun S3 origin to emit its Merge transition
                'merge1'   => ['', 'T2_3_6'],
            ],
            'S4' => [
                'merge'    => 'T4_5_6',
                'merge0'   => ['', 'T4_5_6'], // S4 rerun after S5 activates Merge T4_5_6
                'merge1'   => ['', 'T4_5_6'], // Neither Sync or Merge are active yet, S4 does not rerun
            ],
            'S5' => [
                'merge'    => ['', 'T4_5_6'],
                'merge0'   => 'T4_5_6',
                'merge1'   => 'T4_5_6', // Both Sync or Merge are active now, S4 can not rerun
            ],
        ];
        return $this ;
    }
}
