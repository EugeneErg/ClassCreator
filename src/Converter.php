<?php

declare(strict_types=1);

namespace EugeneErg\ClassCreator;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

final class Converter
{
    private const TYPE_MAP = [
        'int' => 'integer',
    ];

    private static ?self $instance = null;
    private array $converters = [];

    public static function instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** @param callable $callback */
    public function register($callback, ?string $to = null): void
    {
        $reflectionMethod = $this->getReflection($callback);
        $to = $to ?? $this->getReturnTypes($reflectionMethod, false)[0];
        $to = self::TYPE_MAP[$to] ?? $to;
        $types = $this->getParameterType($reflectionMethod, 0);

        if (count($types) === 0) {
            $types = ['NULL'];
        }

        foreach ($types as $from) {
            $this->converters[self::TYPE_MAP[$from] ?? $from][$to] = $callback;
        }
    }

    public function convert(array $types, $value = null)
    {
        $type = gettype($value);

        if (in_array($type, $types, true)) {
            return $value;
        }

        if (is_object($value)) {
            foreach ($types as $type) {
                if ($value instanceof $type) {
                    return $value;
                }
            }

            $byClass = $this->converters[get_class($value)] ?? [];
        } else {
            $byClass = [];
        }

        $byType = $this->converters[$type] ?? [];
        $callbacks = array_intersect_key(array_merge($byType, $byClass), array_flip($types));
        $callback = reset($callbacks);

        if ($callback === false && (is_array($value) || is_null($value))) {
            $callback = $this->getClassCallback($types);
        }

        return DependencyInjector::instance()->call($callback, [$value]);
    }

    public function canConvert(string $type, $value = null): bool
    {
        $from = is_object($value) ? get_class($value) : gettype($value);

        return isset($this->converters[$from][$type]);
    }

    public function convertArgumentsAccordingToParameters(
        array $arguments,
        array $parameters,
        ?string $self = null
    ): array {
        $result = [];

        foreach (array_reverse($parameters) as $number => $parameter) {
            /** @var ReflectionParameter $parameter */
            $name = $parameter->getName();
            $value = $arguments[$name] ?? $arguments[$number] ?? null;
            $exists = array_key_exists($name, $arguments) || array_key_exists($number, $arguments);

            if (!$exists && $parameter->isDefaultValueAvailable() && count($result) !== 0) {
                $result[] = $parameter->getDefaultValue();

                continue;
            }

            $types = $this->getAllTypes($parameter->hasType() ? $parameter->getType() : null);
            $pos = $self === null ? false : array_search('self', $types, true);

            if ($pos !== false) {
                $types[$pos] = $self;
            }

            if ($exists) {
                $values = is_array($value) && $parameter->isVariadic() ? array_reverse($value) : [$value];
            } elseif (!$parameter->isDefaultValueAvailable() && !$parameter->isVariadic()) {
                $values = [$value];
            } else {
                $values = [];
            }

            foreach ($values as $value) {
                $result[] = Converter::instance()->convert($types, $value);
            }
        }

        return array_reverse($result);
    }

    private function getReturnTypes(ReflectionFunctionAbstract $reflectionMethod, bool $withNull = true): array
    {
        return $this->getAllTypes(
            $reflectionMethod->hasReturnType() ? $reflectionMethod->getReturnType() : null,
            $withNull
        );
    }

    private function getParameterType(ReflectionFunctionAbstract $reflectionMethod, $parameterNameOrNumber, bool $withNull = true): array
    {
        foreach ($reflectionMethod->getParameters() as $number => $parameter) {
            if ($number === $parameterNameOrNumber || $parameter->getName() === $parameterNameOrNumber) {
                return $this->getAllTypes($parameter->hasType() ? $parameter->getType() : null, $withNull);
            }
        }

        return [];
    }

    private function getAllTypes(?ReflectionType $type, bool $withNull = true): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = self::TYPE_MAP[$type->getName()] ?? $type->getName();


            return $withNull && $type->allowsNull() ? ['NULL', $typeName] : [$typeName];
        }

        $types = array_map(
            fn (ReflectionType $type): string => self::TYPE_MAP[$type->getName()] ?? $type->getName(),
            $type->getTypes()
        );

        if ($withNull && $type->allowsNull()) {
            $types[] = 'NULL';
        }

        return $types;
    }

    /**
     * @param $callback
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     */
    private function getReflection($callback): ReflectionFunctionAbstract
    {
        if (is_callable($callback)) {
            return new ReflectionFunction(Closure::fromCallable($callback));
        }

        [$class, $method] = $callback;

        return new ReflectionMethod($class, $method);
    }

    private function getClassCallback(array $types): ?Closure
    {
        foreach ($types as $type) {
            $result = $this->getConverterMethod('NULL', $type);

            if ($result !== null) {
                $this->converters['NULL'][$type] = $result;
                $this->converters['array'][$type] = $result;
            }

            return $result;
        }

        return null;
    }

    public function getConverterMethod(string $from, string $to): ?Closure
    {
        if (isset($this->converters[$from][$to])) {
            return $this->converters[$from][$to];
        }

        if (class_exists($to) && in_array($from, ['array', 'NULL'], true)) {
            return function (?array $arguments = []) use ($to): object {
                $class = new ReflectionClass($to);
                $constructor = $class->getConstructor();

                return $constructor === null
                    ? new $to()
                    : $class->newInstanceArgs(
                        Converter::instance()->convertArgumentsAccordingToParameters(
                            $arguments ?? [],
                            $constructor->getParameters(),
                            $to
                        )
                    );
            };
        }

        return null;
    }
}
