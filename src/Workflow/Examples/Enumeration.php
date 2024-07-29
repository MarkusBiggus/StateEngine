<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

/**
 * Base class for Type Enumerations.
 *
 */
class Enumeration
{
    /**
     * Returns the value of this enumeration as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->_;
    }
    /**
     * Returns the names of constants from calling class
     *
     * @return array
     */
    public static function getNames(): array
    {
        $obj = new \ReflectionClass(get_called_class());
        $constants = $obj->getConstants();
        unset($constants['MAX']);
        return array_flip($constants);
    }
    /**
     * getName
     * Get name of a value
     *
     * @param string $value - must match const name
     * @return string
     */
    public static function getName($value)
    {
        $constants = self::getNames();
        return $constants[$value] ?? null;
    }
    /**
     * getValue
     * Get value of name
     *
     * @param String $name - must match a const name
     * @return Int
     */
    public static function getValue($name): ?string
    {
        $obj = new \ReflectionClass(get_called_class());
        $constants = $obj->getConstants();
        return $constants[$name] ?? null;
    }
    /**
     * getMAX
     * Get max number of class constant value
     *
     * @return string
     */
    public static function getMAX()
    {
        $obj = new \ReflectionClass(get_called_class());
        $constants = $obj->getConstants();
        return $constants['MAX'] ?? null;
    }
}
