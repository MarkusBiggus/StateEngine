<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

//use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ComboS1State extends ExmplState // implements WorkflowStateContract
{
//    use ExmplStateTrait;

    protected $thisState = 'S1';
    /**
     * Cycle
     * used to transition differently on each execution
     * @var Int
    private int $Cycle = 0;
     */

    /**
     * run
     * Perform State processing
     * Emit the transition when specified for this test
     * otherwise, relies on the default single transition
     *  defined in the model to next State(s),
     * or Null transition ends this path through the model
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $WorkflowApp app context being processed
     *
     * @return WorkflowAppContract $WorkflowApp
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        if (isset($appContext->TestSEQTransitions[self::thisState][$appContext->TestSEQ])) {
            if (is_array($appContext->TestSEQTransitions[self::thisState][$appContext->TestSEQ])) {
                $transition = $appContext->TestSEQTransitions[self::thisState][$appContext->TestSEQ][$this->Cycle++];
            } else {
                $transition = $appContext->TestSEQTransitions[self::thisState][$appContext->TestSEQ];
            }
            $appContext->ResetTransitions($transition);
        }

        $appContext->StatePath .= '->'.self::thisState;

        if ($Workflow::WorkflowDebug) {
            echo(self::thisState.": $appContext->StatePath<br/>");
        }

        return $appContext;
    }
     */
}
