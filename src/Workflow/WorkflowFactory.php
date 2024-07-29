<?php

namespace MarkusBiggus\StateEngine\Workflow;

use RuntimeException;
use Throwable;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;

/**
 * WorkflowFactory
 * Called by Engine to instantiate the Workflow class object
 * Workflow class has a 'RunState' method, called by Engine dispatcher to
 * execute the State which emit the Transition to the next State(s).
 */
class WorkflowFactory
{
    /**
     * Workflow
     * Instantiate the Workflow class object in App's namespace
     *
     *  $class_name = get_class($this);
     *  $reflection_class = new \ReflectionClass($class_name);
     *  $namespace = $reflection_class->getNamespaceName();
     *
     * @param String $Workflow name
     *
     * @return WorkflowContract
     */
    public static function Make(string $Workflow): WorkflowContract
    {
        // $reflect = new \ReflectionClass(get_called_class());
        // $reflect->getShortName();
        // $class_name = get_class($Workflow);
        $reflection_class = new \ReflectionClass(get_called_class());
        $namespace = $reflection_class->getNamespaceName();
        $WorkflowClass =   $namespace.'\\'.$Workflow.'Workflow';

        if (! class_exists($WorkflowClass)) {
            throw new RuntimeException(__method__.": Invalid workflow name: $Workflow. No class: $WorkflowClass");
        }

        try {
            return new $WorkflowClass();
        } catch (Throwable $e) {
            throw new RuntimeException(__method__.": Invalid Workflow $WorkflowClass:". $e->getMessage());
        }
    }
}
