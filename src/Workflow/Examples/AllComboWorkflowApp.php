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
class AllComboWorkflowApp extends WorkflowApp implements WorkflowAppContract
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
         * This model combines all special transitions.
         * A Split transition is also a Merge.
         * A Fork & Sync each include the Split/Merge and one other transition.
         *
         * All states after S1 emit their default (only) transition
         *
         * fork : I->S1->S1-S4->S1->S2->S3->S4
         */
        $this->TestSEQTransitions = [
            'S1' => [
                'fork'   => ['T1_4','T1_2,T1_3','T1_SM'], // Activate Fork 1st - works
                'merge'  => ['T1_2,T1_3','T1_4','T1_SM'], // Activate Merge 1st - Workflow stalls
                'split'  => ['T1_SM','T1_2,T1_3','T1_4'], // Activate Split (also Fork) 1st - Workflow stalls
                'simple' => ['T1_3','T1_SM','T1_2','T1_4'], // Activate simple transition - Workflow stalls
            ],
        ];
        return $this ;
    }
}
