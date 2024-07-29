<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Workflow;
use MarkusBiggus\StateEngine\Workflow\WorkflowAppFactory;

/**
 * DynoWorkflow
 *
 * Dynamic Workflow model for running tests.
 * DynoFactory has models and test sequences for a variety of
 * test scenarios without the overhead of separate classes for each.
 * Not intended as a use case for te StateEngine, as the other
 * example models are.
 */
class DynoWorkflow extends Workflow implements WorkflowContract
{
    /**
     * WorkflowDebug output
     *
     * @const WorkflowDebug
     */
    public const bool WorkflowDebug = false; // true; //

    /**
     * cacheable
     * Dynamic models aren't cached
     *
     * @var Bool $cacheable
     */
    protected bool $cacheable = false;
    /*
     * StateModel of this Workflow
     *
     * @var Array $StateModel
     */
    public array $StateModel = [
        'Workflow' => 'Dynamic',
        'StatePrefix' => 'Exmpl', // prefix on tests class name
    ];

    /**
     * Valid Model parts to edit
     *
     * @var  array
     */
    static array $ValidModelParts = [
                                 'Workflow',
                                 'StatePrefix',
                                 'States',
                                 'Idle',
                                 'StartState',
                                 'TerminalState',
                                 'StateTransitions',
                                 'Splits',
                                 'Forks',
                                 'Syncs',
                                 'Merges',
                                 'Parameters',
                                ];
    /**
     * CreateAppContext
     * Create the context for this workflow (has all the Workflow bespoke logic & non-state procedures)
     *
     * Called by Engine->StartWorkflow
     *
     * @param StateEngine $Engine to run this workflow
     * @param Array $params for app context, etc
     *
     * @return WorkflowAppContract $appContext or null when workflow is to be abandoned before running
     */
    public function CreateAppContext(StateEngine $Engine, array $params): ?WorkflowAppContract
    {
        if (self::WorkflowDebug) {
            echo "<strong>CreateAppContext</strong> ".$this->GetName()." App<br/>";
        }

        // Call other App init procedures if necessary (created in this object)

        $appContext = WorkflowAppFactory::Make($this)->InitAppContext($params) ;

        return $appContext ;
    }

    /**
     * EditModel
     * Replace any top level part of the Model
     *
     * Used to set up tests dynamically rather than a separate Workflow & App for
     * every model scenario being tested.
     *
     * @param Array $ModelParts of Model to replace ['ModelPart' => [...] ]
     *
     * @return WorkflowContract
     */
    public function EditModel(array $ModelParts): WorkflowContract
    {
        reset($ModelParts);
        $Parts = array_keys($ModelParts);
        $invalidParts = array_diff($Parts, self::$ValidModelParts);
        if (empty($invalidParts)) {
            $this->StateModel = array_replace($this->StateModel, $ModelParts);
            return $this;
        }
        /**
         * Error: invalid Parts present
         */
        throw new \RuntimeException('Invalid Workflow Model parts specified: '. implode(', ', $invalidParts));
    }
}
