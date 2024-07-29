<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

class ReferenceS2State extends ExmplState implements WorkflowStateContract
{
    protected $thisState = 'S2';
    /**
     * isDelayed
     * used to create lag in model path on one branch.
     * 2nd time emit transition to avoid stalling Workflow.
     * @var Bool
     */
    //private bool $isDelayed = true;
    /**
     * run
     * Perform State processing and set Transition(s) to next State(s) before returning
     * $appContext->ResetTransitions must be called at least once.
     *
     * @param WorkflowContract $Workflow being processed
     * @param WorkflowAppContract $AppContext Workflow context
     *
     * @return WorkflowAppContract $AppContext
    public function run(WorkflowContract $Workflow, WorkflowAppContract $appContext): WorkflowAppContract
    {
        if ($appContext->TestSEQ == 'forksync' || $appContext->TestSEQ == 'forkmerge') {
            $transitions = explode(',', $appContext->TestSEQTransitions['S2'][$appContext->TestSEQ]);
        } else {
            $transitions[] = $appContext->TestSEQTransitions['S2'][$appContext->TestSEQ];
        }


        if ($this->isDelayed && isset($transitions[1])) {
            $appContext->StatePath .= '->S2(wait)';
            if ($Workflow::WorkflowDebug) {
                echo("S2 1st: $appContext->StatePath<br/>");
            }
            $this->isDelayed = false; // transition next time
            $appContext->ResetTransitions($transitions[1]);
            return $appContext;
        }

    //    $appContext->StatePath .= isset($transitions[1]) ? '->S2b' : '->S2';
        $appContext->StatePath .= '->S2';

        if ($Workflow::WorkflowDebug) {
            echo("S2 path: $appContext->StatePath<br/>");
        }
        //Test FORK
        //    return $appContext->ResetTransitions(ReferenceTransitionMask::T2_3)  //  +StatePath: "InitialS S1 S2 S3 S4 S8(terminal) "
        //                      ->SetTransitions(ReferenceTransitionMask::T2_4);
        //Test SPLIT
        //    $appContext->ResetTransitions(ReferenceTransitionMask::T2_3_4); // +StatePath: "InitialS S1 S2 S3 S4 S8(terminal) "

        $appContext->ResetTransitions($transitions[0]);
        //Test MERGE after FORK
        //        return $appContext->ResetTransitions(ReferenceTransitionMask::T2_5) //  +StatePath: "InitialS S1 S2 S5 S6 S7 S8(terminal) "
        //                          ->SetTransitions(ReferenceTransitionMask::T2_6);
        return $appContext;
    }
     */
}
