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
class IdleWaitWorkflowApp extends WorkflowApp implements WorkflowAppContract
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
         *
         * idlewait : I->S1->S2->S3->S2->S4->S2->S5(terminal)
         * idlestop  : I->S1->S2->S2
         * idlenowait : I->S1->S3->S4->S2->S5(terminal)
         * forksplit : I->S1->S3->S4->S2->S5(terminal)
         */
        $this->TestSEQTransitions = [
            'S1' => [
                'idlewait'   => 'T1_2, T1_3ab', // fork
                'idlestop'   => 'T1_2', //left fork only - stalls
                'idlenowait' => 'T1_3ab', //right fork only - stalls
                'forksplit'  => 'T1_2, T1_3ab', //fork to split
            ],
            'S2' => [
                'idlewait'   => 'T2_5',
                'idlestop'   => 'T2_5',
                'idlenowait' => 'T2_5',
                'forksplit'  => 'T2_5',
            ],
            'S3' => [
                'idlewait'   => 'T3_4',
                'idlestop'   => '',
                'idlenowait' => 'T3_4',
                'forksplit'  => 'T3_4',
            ],
            'S4' => [
                'idlewait'   => 'T4_2',
                'idlestop'   => '',
                'idlenowait' => 'T4_2',
                'forksplit'  => 'T4_2',
            ],
        ];
        return $this ;
    }
}
