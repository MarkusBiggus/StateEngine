<?php

namespace MarkusBiggus\StateEngine\Workflow;

use RuntimeException;
use Throwable;

use MarkusBiggus\StateEngine\Contracts\WorkflowContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;

/**
 * WorkflowAppFactory
 * Called by Workflow->AppContext to create the WorkflowApp context
 */
class WorkflowAppFactory
{
    /**
     * Make
     * Application volatile attributes.
     *
     * @param WorkflowContract
     * @param String $AppSuffix for the context class name: WorkflowAppSuffix
     *
     * @return WorkflowAppContract $appContext
     */
    public static function Make(WorkflowContract $Workflow, String $AppSuffix = 'App'): WorkflowAppContract
    {
        $class_name = get_class($Workflow);
        // $reflection_class = new \ReflectionClass($class_name);
        // $namespace = $reflection_class->getNamespaceName();
        // $WorkflowAppClass =  $namespace.'\\'.$Workflow->StateModel['Workflow'].'WorkflowApp';

        $WorkflowAppClass =  $class_name.$AppSuffix;

        if (! class_exists($WorkflowAppClass)) {
            throw new RuntimeException(__method__.": Invalid workflow name: $Workflow->StateModel['Workflow']. No App class: $WorkflowAppClass");
        }

        try {
            return new $WorkflowAppClass($Workflow);
        } catch (Throwable $e) {
            throw new RuntimeException(__method__.": Invalid WorkflowApp $WorkflowAppClass:". $e->getMessage());
        }
    }
}
