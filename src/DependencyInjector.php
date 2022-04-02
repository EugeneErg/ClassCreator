<?php

declare(strict_types=1);

namespace EugeneErg\ClassCreator;

use Closure;
use ReflectionFunction;
use ReflectionMethod;

final class DependencyInjector
{
    private Converter $converter;

    public function __construct(Converter $converter)
    {
        $this->converter = $converter;
    }

    public function create(string $className, array $arguments = []): object
    {
        return $this->converter->convert([$className], $arguments);
    }

    public function singleton(string $className, $callback = null): void
    {
        if ($callback === null) {
            $callback = $this->converter->getConverterMethod('NULL', $className);
        }

        $this->converter->register(function (?array $arguments = []) use ($callback) {
            static $result;

            if (!empty($arguments)) {
                return $this->call($callback, $arguments);
            }

            if (!isset($result)) {
                $result = $this->call($callback);
            }

            return $result;
        }, $className);
    }

    public function call($function, array $arguments = [])
    {
        if (
            ((is_string($function) && class_exists($function)) || is_object($function))
            && method_exists($function, '__invoke')
        ) {
            $function = [$function, '__invoke'];
        }

        if (
            is_array($function)
            && count($function) === 2
            && isset($function[0], $function[1])
            && (is_string($function[0]) || is_object($function[0]))
            && method_exists($function[0], $function[1])
        ) {
            if ($function[0] instanceof Closure) {
                $reflectionFunction = new ReflectionFunction($function[0]);
                $reflectionClass = $reflectionFunction->getClosureScopeClass();

                if ($reflectionClass !== null) {
                    $self = $reflectionClass->getName();
                }
            }

            $method = new ReflectionMethod(...$function);
            $parameters = $this->converter->convertArgumentsAccordingToParameters(
                $arguments,
                $method->getParameters(),
                $self ?? null
            );

            return $method->isStatic()
                ? call_user_func_array($function, $parameters)
                : $method->invokeArgs(
                    is_object($function[0]) ? $function[0] : $this->create($function[0]),
                    $parameters
                );
        }

        $method = new ReflectionFunction(Closure::fromCallable($function));

        return $method->invokeArgs(
            $this->converter->convertArgumentsAccordingToParameters(
                $arguments,
                $method->getParameters()
            )
        );
    }
}
