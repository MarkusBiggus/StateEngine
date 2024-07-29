<?php

namespace MarkusBiggus\StateEngine\Workflow;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\WorkflowAppFactory;
use MarkusBiggus\StateEngine\Workflow\WorkflowStateFactory;

use MarkusBiggus\StateEngine\Traits\WorkflowTrait;

abstract class Workflow implements WorkflowContract
{
    use WorkflowTrait;
    /**
     * WorkflowDebug output
     *
     * @var Bool WorkflowDebug
     */
    public const bool WorkflowDebug =  false; // true; //

    /**
     * isDebugging
     * Should Debug info be logged by Workflow states
     *
     * @return Bool Workflow debug status
     */
    public static function isDebugging(): bool
    {
        return self::WorkflowDebug;
    }

    /*
     * Workflow Model version Major.Minor, previous builds are compatible with same Major version
     *
     * @const string $WFVersion
     */
    public const WFVersion = '1.0';

    /*
     * Workflow Model Build increment every change release to production - a build may not be be a new version
     *
     * @const string $WFBuild
     */
    public const WFBuild = '1';

    /**
     * StateModel
     * Placeholder to be overwritten by actual Workflow model
     *
     * @var Array
     */
    public array $StateModel = [
        'Workflow' => 'Foundation', // prefix on this class name
    ];

    /**
     * GetRunableState
     * Get the State object ready to run, either Singleton or Prototype mode.
     *
     * @param String $StateName is state to run
     * @param Bool $Singleton when False State is not kept - default state is Singleton
     *
     * @return WorkflowStateContract
     */
    protected function GetRunableState(string $StateName, bool $Singleton): WorkflowStateContract
    {
        if ($Singleton) {
            if (! isset($this->Handlers[$StateName])) {
                $this->Handlers[$StateName] = WorkflowStateFactory::make($this, $StateName);
            }
            $handler = $this->Handlers[$StateName];
        } else {
            $handler = WorkflowStateFactory::make($this, $StateName);
        }

        if(self::WorkflowDebug) {
            echo "<br/><strong>RunState: ". get_class($handler) ."</strong> ".($Singleton) ? 'Singleton' : 'Prototype'. "<br/>";
        }

        return $handler;
    }

    /**
     * RunState (maybe be tailored for your App)
     * Each State called by Engine to run as required.
     * handler name in Model['States'] when specified,
     * otherwise class is 'State' appended to State name
     *
     * Each State handler uses ResetTransitions() or SetTransitions() to emit its transitions.
     * On return, the Engine is passed all transitions.
     *   The Engine doesn't transition until end of dispatch cycle, after all active states have emitted transitions.
     *
     * @param StateEngine $Engine
     * @param WorkflowAppContract $appContext is current app context for this Engine
     * @param String $StateName is state to run
     * @param Bool $Singleton when False State is not kept - default state is Singleton
     *
     * @return WorkflowAppContract
     */
    public function RunState(StateEngine $Engine, WorkflowAppContract $appContext, string $StateName, bool $Singleton = true): WorkflowAppContract
    {
        $handler = $this->GetRunableState($StateName, $Singleton);

        $appContext->ResetTransitions(0) ;
        $handler->run($this, $appContext); // State and Status are set by State handler
        // if (! $Singleton) {
        //     unset($handler);
        // }

        if(self::WorkflowDebug) {
            echo "<strong>RunState</strong> emitted TransitionMask: ". decbin($appContext->GetTransitions()) ."<br/>";
        }
        $Engine->StateTransition($appContext->GetTransitions()); // transitions set by the State handler just run
        if (isset($this->Logger)) {
            $this->Logger->LogState($Engine->GetWorkflowStateMask()); // must log last transitions too, for resume
        }

        return $appContext;
    }

    /**
     * CreateAppContext
     * This template method cab be tailored for the App and overriden by
     * Real Workflow that extends this class.
     *
     * Create the context for this workflow (has all the Workflow globals & non-state related procedures)
     * Rebuild a previous workflow if the Engine is being resumed, otherwise its a NewInstance
     *
     * Called by StateEngine->StartWorkflow
     *
     * @param StateEngine $Engine to run this workflow
     * @param Array $params for app context, etc
     *
     * @return WorkflowAppContract $appContext or null to abandon this workflow before starting
     */
    public function CreateAppContext(StateEngine $Engine, array $params): ?WorkflowAppContract
    {
        if (self::WorkflowDebug) {
            echo '<strong>CreateAppContext</strong> '.$this->GetName().' App<br/>';
        }

        // Call other App init procedures as necessary (create in extended class)

        $appContext = WorkflowAppFactory::Make($this)->InitAppContext($params) ;

        return $appContext ;
    }
}
