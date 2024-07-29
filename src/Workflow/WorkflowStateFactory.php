<?php

namespace MarkusBiggus\StateEngine\Workflow;

use RuntimeException;
use Throwable;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowStateContract;

/**
 * WorkflowStateFactory
 * Used by Workflow to instantiate the State class object
 * State class has a 'run' method, called by Workflow State method to
 * execute the State which sets the Transitions to the next State.
 *
 */
class WorkflowStateFactory
{
    /**
     * Make
     * The State class names all end with suffix 'State', unless handler specified in State model to override name
     *
     * @param WorkflowContract $Workflow model this state is part of
     * @param String $WorkflowState is name of the State in the Workflow Model (eg. S1x -> App\Workflow\S1xState)
     *
     * @return WorkflowStateContract
     */
    public static function Make(WorkflowContract $Workflow, string $WorkflowState): WorkflowStateContract
    {
        $class_name = get_class($Workflow);
        $reflection_class = new \ReflectionClass($class_name);
        $namespace = $reflection_class->getNamespaceName();
        $StateClass =   $namespace.'\\'.$WorkflowState;

        if (! class_exists($StateClass)) {
            throw new RuntimeException(__method__.": Invalid Workflow. No State class: $StateClass: ");
        }

        try {
            return new $StateClass($Workflow);
        } catch (Throwable $e) {
            throw new RuntimeException(__method__.": Invalid State class: $StateClass: ". $e->getMessage());
        }
    }
}
