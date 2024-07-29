<?php

namespace MarkusBiggus\StateEngine\Facades;

class DynoModel extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \MarkusBiggus\StateEngine\Workflow\Examples\DynoWorkflow::class;
    }
    /**
     * Resolve a new instance for the facade
     *
     * @return mixed
     */
    public static function new()
    {
        static::clearResolvedInstance(static::getFacadeAccessor());

        return static::getFacadeRoot();
    }
}
