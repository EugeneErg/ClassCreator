<?php namespace EugeneErg\ClassCreator;

use ReflectionClass;
use ReflectionException;

class ClassComparisonService
{
    private const CLASS_TYPE_TRAIT = 'trait';
    private const CLASS_TYPE_INTERFACE = 'interface';
    private const CLASS_TYPE_CLASS = 'class';

    /**
     * @param string $classA
     * @param string $classB
     * @return int|null
     * @throws ReflectionException
     */
    public function getAffinityLevel(string $classA, string $classB): ?int
    {
        if ($classA === $classB) {
            return 0;
        }

        if (!is_subclass_of($classA, $classB)) {
            return null;
        }

        $classTypeA = $this->getClassType($classA);
        $classTypeB = $this->getClassType($classB);
        $parents = $classTypeA === $classTypeB ? [] : class_implements($classA);
        $parent = get_parent_class($classA);
        $result = null;

        if ($parent === false) {
            $parents[] = $parent;
        }

        foreach ($parents as $implement) {
            $level = $this->getAffinityLevel($implement, $classB);

            if ($result === null || $result > $level) {
                $result = $level;
            }
        }

        return $result;
    }

    /**
     * @param string $class
     * @return string
     * @throws ReflectionException
     */
    public function getClassType(string $class): string
    {
        $reflectionClass = new ReflectionClass($class);

        if ($reflectionClass->isTrait()) {
            return self::CLASS_TYPE_TRAIT;
        }

        if ($reflectionClass->isInterface()) {
            return self::CLASS_TYPE_INTERFACE;
        }

        return self::CLASS_TYPE_CLASS;
    }
}
