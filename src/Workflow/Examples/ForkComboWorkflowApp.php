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
class ForkComboWorkflowApp extends WorkflowApp implements WorkflowAppContract
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
         *          fork : I->S1->S3->S4->S5->S2->S6
         *         fork1 : I->S1->S3->S4->S5->S2->S5->S6
         *         fork2 : I->S1->S3->S4->S5->S5->S2->S6
         * splitsyncmerge : I->S1->S3->S4->S5->S2->S6
         * syncmergesplit : I->S1->S1->S3->S4->S5->S2->S6
         */
        $this->TestSEQTransitions = [
            'S1' => [
                'fork'    => ['T1_3_4, T1_6, T1_5_2'], // Emit all transitions in 1 cycle
                'fork1'   => ['T1_3_4, T1_6, T1_5_2'],
                'fork2'   => ['T1_3_4, T1_6, T1_5_2'],
                'splitsyncmerge' => ['T1_3_4', 'T1_6', 'T1_5_2'], // Emit 1 transition each cycle
                'syncmergesplit' => ['T1_6, T1_5_2', 'T1_3_4'], // First cycle does not transition
            ],
            'S5' => [
                'fork'    => 'T1_5_2, T5_6', // emit both transitions in 1 cycle
                'fork1'   => ['T1_5_2', 'T5_6'], // emit 1 transition each cycle - Merge, Sync
                'fork2'   => ['T5_6', 'T1_5_2'], // emit 1 transition each cycle - Sync, Merge
                'splitsyncmerge' => 'T1_5_2, T5_6',
                'syncmergesplit' => ' T5_6, T1_5_2',
            ],
        ];
        return $this ;
    }
}
