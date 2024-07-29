<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use Illuminate\Support\Facades\Log;

use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;
use MarkusBiggus\StateEngine\Workflow\WorkflowApp;

/**
 * The $appContext passed into the Engine
 * Variable in this object are the context of the App using this StateModel.
 *
 * It is possible to have any number of App variants using the same StateModel.
 * For testing, there is a Controller variant for each of the StateModel transition types being tested.
 */
class DynoWorkflowApp extends WorkflowApp implements WorkflowAppContract
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
     * $LastCycle
     * Last Dispatch Cycle set by Terminal state
     *
     * @var Int
     */
    public $LastCycle = 0;

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
        // TestSEQ is name of test being run
        $this->TestSEQ = $params[0];

        $eMessage = 'to run test: '.$this->TestSEQ;
        Log::info("InitAppContext $eMessage");
        if (self::WorkflowDebug) {
            echo "<br/><strong>InitAppContext</strong> $eMessage<br/>";
        }

        $this->StatePath = 'I';
        /**
         * Sequences for each test
         * See CreateAppContext in the model for test modifications to trigger exceptions
         * Set by controller $appContext->EditTestSEQ('testSEQ');
         */
        $this->TestSEQTransitions = [
        ];
        return $this ;
    }

    /**
     * EditTestSEQ
     * Replace any top level part of TestSEQTransitions
     * The top level names are States defined in the Model.
     *
     * Used to set up tests dynamically rather than a separate Workflow & App for
     * every model scenario being tested.
     *
     * @param Array $TestSEQ of TestSEQTransitions to replace ['State' => [transitions...] ]
     *
     * @return WorkflowApp
     */
    public function EditTestSEQ(array $TestSEQ): WorkflowApp
    {
        // reset($TestSEQ);
        // $Parts = array_keys($TestSEQ);
        // $invalidParts = array_diff($Parts, self::$ValidTestParts);
        // if (empty($invalidParts)) {
            $this->TestSEQTransitions = array_replace($this->TestSEQTransitions, $TestSEQ);
            return $this;
        //}
        /**
         * Error: invalid Parts present
         */
        //throw new \RuntimeException('Invalid Workflow Model parts speciied: '. implode(', ', $invalidParts));
    }
    /**
     * DeleteTestSEQ
     * Delete any top level part of TestSEQTransitions
     * The top level names are States defined in the Model.
     *
     * Used to set up tests dynamically rather than a separate Workflow & App for
     * every model scenario being tested.
     *
     * @param Array $TestSEQ of TestSEQTransitions to replace ['State']
     *
     * @return WorkflowApp
     */
    public function DeleteTestSEQ(array $TestSEQ): WorkflowApp
    {
        foreach ($TestSEQ as $State) {
            unset ($this->TestSEQTransitions[$State]);
        }
        return $this;
    }
}
