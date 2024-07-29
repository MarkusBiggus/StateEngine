<?php

namespace MarkusBiggus\StateEngine\Workflow;

use MarkusBiggus\StateEngine\Traits\WorkflowAppTrait;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * This WorkflowApp template contains all bespoke logic for the Workflow.
 * It extends the basic Workflow context needed to run any Workflow.
 * The app and it's State handler decide on which transitions to emit and when.
 *
 * When resumed, the Engine DispatchLastTransitions informs a State what transition made it active.
 * This can be used for 'retry' States, which are Idle states, to transition back to the State that had a retryable error,
 * like an API call, that may now work.
 */
//abstract class WorkflowApp extends Workflow implements
abstract class WorkflowApp implements
    WorkflowAppContract
{
    use WorkflowAppTrait;
    /**
     * __construct
     *
     */
    public function __construct(protected WorkflowContract $Workflow)
    {
    }

    /**
     * InitAppContext (template)
     * Initialise the context object for this Workflow
     *
     * Called by Workflow->CreateAppContext
     *
     * @param Array $params
     *
     * @return WorkflowAppContract $appContext or null to abandon this workflow before starting
     */
    public function InitAppContext(array $params): ?WorkflowAppContract
    {
        if (self::WorkflowDebug) {
            echo '<strong>InitAppContext</strong> '.$this->Workflow->GetName().' App<br/>';
        }

        // Initialse stuff from $params

        return $this ;
    }
}
