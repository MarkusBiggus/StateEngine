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
class PipelineWorkflowApp extends WorkflowApp implements WorkflowAppContract
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
         * Pipeline states emit no transition - default transition from model is used for each
         * See CreateAppContext in Workflow for model edits for tests Pipe2 & Pipe3
         *
         * pipe1 : I->P1->P2->P3->P4->P5->P6->P7(terminal)
         * pipe2 : I->P1->P2->P3->P4->P5->P6->P6->P7(terminal)
         * pipe3 : I->P1->P2->P3->P4->P5->P6->P7(terminal)
         */
        $this->TestSEQTransitions = [
            'P1' => [
                'pipe1'   => '', // split with sync from P4 & P5
                'pipe2'   => '', // split with no sync from P4 & P5
                'pipe3'   => '', // split with no default transition from P4 (path ends)
            ],
        ];
        return $this ;
    }
}
