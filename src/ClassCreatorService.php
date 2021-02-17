<?php namespace EugeneErg\ClassCreator;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use function is_string;
use function is_array;

final class ClassCreatorService
{
    /** @var array */
    private $converters = [];
    /** @var Branch[] */
    private $nameSpaceTree = [];
    /** @var Object[][] */
    private $classes = [];
    /** @var array */
    private $functions = [];
    /** @var self[] */
    private static $instance;
    /** @var ClassComparisonService */
    private $classComparisonService;
    /** @var ValueIndexService */
    private $valueIndexService;

    private function __construct()
    {
        $this->classComparisonService = new ClassComparisonService();
        $this->valueIndexService = new ValueIndexService();
    }

    /**
     * @return static
     */
    public static function instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $className
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    public function create(string $className, array $arguments = [])
    {
        $class = new ReflectionClass($className);
        $constructor = $class->getConstructor();

        if (!$constructor) {
            return new $className();
        }

        return $class->newInstanceArgs(
            $this->convertArgumentsAccordingToParameters(
                $arguments,
                $constructor->getParameters(),
                $className,
                $constructor->getName()
            )
        );
    }

    /**
     * @param string $className
     * @param array $arguments
     * @return object
     * @throws ReflectionException
     * @throws Exception
     */
    public function createSingle(string $className, array $arguments = []): object
    {
        $class = new ReflectionClass($className);
        $constructor = $class->getConstructor();
        $parameters = $constructor
            ? $this->convertArgumentsAccordingToParameters(
                $arguments,
                $constructor->getParameters(),
                $className,
                $constructor->getName()
            )
            : [];
        $index = $this->valueIndexService->getIndex(...$parameters);

        if (!isset($this->classes[$className][$index])) {
            $this->classes[$className][$index] = $class->newInstanceArgs($parameters);
        }

        return $this->classes[$className][$index];
    }

    /**
     * @param callable $function
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    public function call(callable $function, array $arguments = [])
    {
        list($class, $method) = $this->getClassMethodByCallable($function);
        $reflectionFunction = $this->getReflectionByCallable($function);

        return $reflectionFunction->invokeArgs(
            $this->convertArgumentsAccordingToParameters(
                $arguments,
                $reflectionFunction->getParameters(),
                $class,
                $method
            )
        );
    }

    /**
     * @param callable $function
     * @param string[] $types
     * @return bool
     * @throws ReflectionException
     */
    public function isCompatible(callable $function, array $types): bool
    {
        $reflectionFunction = $this->getReflectionByCallable($function);
        list($className, $methodName) = $this->getClassMethodByCallable($function);
        $parameters = $reflectionFunction->getParameters();

        foreach (array_reverse($parameters) as $number => $parameter) {
            $name = $parameter->getName();
            $valueType = $arguments[$name] ?? $arguments[$number] ?? null;
            $exists = array_key_exists($name, $types) || array_key_exists($number, $types);

            if (!$exists && !$parameter->isDefaultValueAvailable()) {
                if (!$parameter->isVariadic()
                    && !$parameter->allowsNull()
                    && $parameter->hasType()
                ) {
                    $result = $this->isConvertible(
                        'NULL',
                        $parameter->getType()->getName(),
                        $className,
                        $methodName,
                        $name
                    );

                    if (!$result) {
                        return false;
                    }
                }
            } elseif ($exists) {
                $values = is_array($valueType) && $parameter->isVariadic() ? array_reverse($valueType) : [$valueType];

                if ($parameter->hasType()) {
                    $type = $parameter->getType()->getName();
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $type = gettype($parameter->getDefaultValue());
                } else {
                    continue;
                }

                foreach ($values as $value) {
                    $result = $this->isConvertible(
                        $value,
                        $type,
                        $className,
                        $methodName,
                        $name
                    );

                    if (!$result) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param callable $function
     * @param array $arguments
     * @return mixed
     * @throws ReflectionException
     * @throws Exception
     */
    public function callSingle(callable $function, array $arguments = [])
    {
        list($class, $method) = $this->getClassMethodByCallable($function);

        $functionIndex = $this->getIndexByCallable($function);
        $reflectionFunction = $this->getReflectionByCallable($function);
        $parameters = $this->convertArgumentsAccordingToParameters(
            $arguments,
            $reflectionFunction->getParameters(),
            $class,
            $method
        );
        $index = $this->valueIndexService->getIndex(...$parameters);

        if (!isset($this->functions[$functionIndex][$index])) {
            $this->functions[$functionIndex][$index] = $reflectionFunction->invokeArgs($parameters);
        }

        return $this->functions[$functionIndex][$index];
    }

    public function isConvertible(string $valueType, string $type, string $class, string $method, string $parameter): bool
    {
        if ($valueType === $type || is_subclass_of($valueType, $type)) {
            return true;
        }

        $converter = $this->getPriorityConverter($type, $class, $method, $parameter, $valueType)
            ?? $this->converterByNameSpace($valueType, $type, $class);

        return $converter !== null || (class_exists($type) && in_array($valueType, ['NULL', 'array'], true));
    }

    /**
     * @param mixed $value
     * @param string $type
     * @param string $class
     * @param string $method
     * @param string $parameter
     * @return mixed
     * @throws Exception
     */
    public function convert($value, string $type, string $class, string $method, string $parameter)
    {
        $valueType = is_object($value) ? get_class($value) : gettype($value);

        if ($valueType === $type || is_subclass_of($valueType, $type)) {
            return $value;
        }

        $converter = $this->getPriorityConverter($type, $class, $method, $parameter, $valueType)
            ?? $this->converterByNameSpace($valueType, $type, $class);

        if ($converter === null) {
            if (class_exists($type) && $value === null || is_array($value)) {
                return $this->createSingle($type, (array) $value);
            }

            throw new ConvertTypeException();
        }

        $valueIndex = $this->valueIndexService->getIndex($value);

        if (!isset($converter->values[$valueIndex])) {
            $method = $converter->method;
            $converter->values[$valueIndex] = $value === null ? $method() : $method($value);
        }

        return $converter->values[$valueIndex];
    }

    /**
     * @param string $type
     * @param string $class
     * @param string $method
     * @param string $parameter
     * @param string $valueType
     * @return object|null
     * @throws ReflectionException
     */
    private function getPriorityConverter(
        string $type,
        string $class,
        string $method,
        string $parameter,
        string $valueType
    ): ?object {
        if (!isset($this->converters[$type])) {
            return null;
        }

        $classes = [];

        foreach ($this->converters[$type] as $className => $classB) {
            if (!isset($classB->converters[$valueType])) {
                continue;
            }

            $level = $this->classComparisonService->getAffinityLevel($class, $className);

            if ($level !== null) {
                $classB->level = $level;
                $classes[] = $classB;
            }
        }

        usort($classes, function(object $classA, object $classB) use($valueType, $method, $parameter): int {
            return $this->compare(
                    isset($classB->converters[$valueType][$method][$parameter]),
                    isset($classA->converters[$valueType][$method][$parameter])
                ) ?? $this->compare(
                    isset($classB->converters[$valueType][$method][null]),
                    isset($classA->converters[$valueType][$method][null])
                ) ?? $this->compare(
                    isset($classB->converters[$valueType][null][null]),
                    isset($classA->converters[$valueType][null][null])
                ) ?? $this->compare(
                    $classA->level,
                    $classB->level
                ) ?? 0;
        });

        return $classes[0]->converters[$valueType][$method][$parameter]
            ?? $classes[0]->converters[$valueType][$method][null]
            ?? $classes[0]->converters[$valueType][null][null]
            ?? null;
    }

    /**
     * @param $valueA
     * @param $valueB
     * @return int
     */
    private function compare($valueA, $valueB): ?int
    {
        $result = $valueA <=> $valueB;

        return $result === 0 ? null : $result;
    }

    /**
     * @param string $valueType
     * @param string $type
     * @param string $class
     * @return object|null
     */
    private function converterByNameSpace(string $valueType, string $type, string $class): ?object
    {
        if (!isset($this->nameSpaceTree[$type])) {
            return null;
        }

        $current = $this->nameSpaceTree[$type];
        $result = $current->getValue()->converters[$valueType] ?? null;
        $routes = explode('\\', $class);

        for ($i = 0; $i < count($routes) - 1; $i++) {
            $route = $routes[$i];

            if (!$current->hasChild($route)) {
                return $result;
            }

            if (isset($current->getValue()->converters[$valueType])) {
                $result = $current->getValue()->converters[$valueType];
            }
        }

        return $result;
    }

    /**
     * @param array $arguments
     * @param ReflectionParameter[] $parameters
     * @param string $className,
     * @param string $methodName
     * @return array
     * @throws Exception
     */
    private function convertArgumentsAccordingToParameters(
        array $arguments,
        array $parameters,
        string $className,
        string $methodName
    ): array {
        $result = [];

        foreach (array_reverse($parameters) as $number => $parameter) {
            $name = $parameter->getName();
            $value = $arguments[$name] ?? $arguments[$number] ?? null;
            $exists = array_key_exists($name, $arguments) || array_key_exists($number, $arguments);

            if (!$exists && !$parameter->isDefaultValueAvailable()) {
                if (!$parameter->isVariadic()) {
                    if ($parameter->allowsNull() || !$parameter->hasType()) {
                        $result[] = $value;
                    } elseif ($parameter->hasType()) {
                        $result[] = $this->convert(
                            null,
                            $parameter->getType()->getName(),
                            $className,
                            $methodName,
                            $name
                        );
                    }
                }
            } elseif (!$exists && $parameter->isDefaultValueAvailable()) {
                if (count($result)) {
                    $result[] = $parameter->getDefaultValue();
                }
            } else {
                $values = is_array($value) && $parameter->isVariadic() ? array_reverse($value) : [$value];

                if ($parameter->hasType()) {
                    $type = $parameter->getType()->getName();
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $type = gettype($parameter->getDefaultValue());
                } else {
                    foreach ($values as $value) {
                        $result[] = $value;
                    }

                    continue;
                }

                foreach ($values as $value) {
                    $result[] = $this->convert(
                        $value,
                        $type,
                        $className,
                        $methodName,
                        $name
                    );
                }
            }
        }

        return array_reverse($result);
    }

    /**
     * @param callable $callback
     * @param string|null $class
     * @param string|null $method
     * @param string|null $parameter
     * @throws ReflectionException
     */
    public function addConverterWithClass(
        callable $callback,
        string $class,
        string $method = null,
        string $parameter = null
    ): void {
        $function = $this->getReflectionByCallable($callback);
        $type = $function->getReturnType()->getName();
        $parameters = $function->getParameters();
        $valueType = count($parameters) ? $function->getParameters()[0]->getType()->getName() : gettype(null);

        if (!isset($this->classes[$type][$class])) {
            $this->converters[$type][$class] = (object) [
                'converters' => [],
            ];
        }

        $this->converters[$type][$class]->convertors[$valueType][$method][$parameter] = (object) [
            'method' => $function->getClosure(),
            'values' => [],
        ];
    }

    /**
     * @param callable $callback
     * @param string|null $nameSpace
     * @throws ReflectionException
     */
    public function addConverter(
        callable $callback,
        string $nameSpace = null
    ): void {
        $function = $this->getReflectionByCallable($callback);
        $typeTo = $function->getReturnType()->getName();
        $parameters = $function->getParameters();
        $typeFrom = count($parameters) ? $function->getParameters()[0]->getType()->getName() : gettype(null);

        if (!isset($this->nameSpaceTree[$typeTo])) {
            $this->nameSpaceTree[$typeTo] = new Branch((object) [
                'converters' => [],
            ]);
        }

        $current = $this->nameSpaceTree[$typeTo];

        if ($nameSpace !== null) {
            $routes = explode('\\', $nameSpace);

            foreach ($routes as $route) {
                if (!$current->hasChild($route)) {
                    $current->addChild(new Branch((object) [
                        'converters' => [],
                    ]), $route);
                }

                $current = $current->getChild($route);
            }
        }

        $current->getValue()->converters[$typeFrom] = (object) [
            'method' => $function->getClosure(),
            'values' => [],
        ];
    }

    /**
     * @param string $interface
     * @param string $class
     * @param string|null $nameSpace
     * @throws ReflectionException
     */
    public function addBind(string $interface, string $class, string $nameSpace = null): void
    {
        $this->addConverter(eval("return function(): $interface {
            return \$this->createSingle('$class');
        };"), $nameSpace);
    }

    /**
     * @param string $interface
     * @param string $class
     * @param string $contextClass
     * @param string|null $method
     * @param string|null $parameter
     * @throws ReflectionException
     */
    public function addBindWithClass(
        string $interface,
        string $class,
        string $contextClass,
        string $method = null,
        string $parameter = null
    ): void {
        $this->addConverterWithClass(eval("return function(): $interface {
            return \$this->createSingle('$class');
        };"), $contextClass, $method, $parameter);
    }

    /**
     * @param Closure $closure
     * @return string
     * @throws ReflectionException
     */
    public function getClosureClassId(Closure $closure): string
    {
        $reflection = new ReflectionFunction($closure);

        return md5(implode(',', [
            $reflection->__toString(),
            $reflection->getFileName(),
            $reflection->getDocComment(),
            $reflection->getStartLine(),
        ]));
    }

    /**
     * @param callable $function
     * @return string[]|null[]
     * @throws Exception
     */
    private function getClassMethodByCallable(callable $function): array
    {
        if (is_string($function)) {
            $method = explode('::', $function);

            if (isset($method[1])) {
                return $method;
            }

            return [null, $method[0]];
        }

        if ($function instanceof Closure) {
            return [null, $this->getClosureClassId($function)];
        }

        if (is_object($function)) {
            return [get_class($function), '__invoke'];
        }

        if (is_array($function)) {
            return [is_object($function[0]) ? get_class($function[0]) : $function[0], $function[1]];
        }

        return [null, null];
    }

    /**
     * @param callable $function
     * @throws ReflectionException
     */
    private function getReflectionByCallable(callable $function): ReflectionFunction
    {
        return new ReflectionFunction(Closure::fromCallable($function));
    }

    /**
     * @param callable $function
     * @return string
     * @throws Exception
     */
    private function getIndexByCallable(callable $function): string
    {
        if (is_object($function)) {
            /** @var object $function */
            return spl_object_hash($function);
        }

        if (is_string($function)) {
            return $function;
        }

        if (is_object($function[0])) {
            return spl_object_hash($function[0]) . '::' . $function[1];
        }

        /** @var array $function */
        return implode('::', $function);
    }
}
