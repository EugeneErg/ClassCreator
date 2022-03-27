<?php

declare(strict_types=1);

namespace EugeneErg\ClassCreator;

use Closure;
use ReflectionFunction;
use ReflectionMethod;

final class DependencyInjector
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function create(string $className, array $arguments = []): object
    {
        return Converter::instance()->convert([$className], $arguments);
    }

    public function singleton(string $className, ?string $callback = null): void
    {
        if ($callback === null) {
            $callback = Converter::instance()->getConverterMethod('NULL', $className);
        }

        Converter::instance()->register(function (?array $arguments = []) use ($callback) {
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
            $method = new ReflectionMethod(...$function);

            return $method->invokeArgs(
                $method->isStatic() || is_object($function[0]) ? $function[0] : $this->create($function[0]),
                Converter::instance()->convertArgumentsAccordingToParameters(
                    $arguments,
                    $method->getParameters()
                )
            );
        }

        $method = new ReflectionFunction(Closure::fromCallable($function));

        return $method->invokeArgs(
            Converter::instance()->convertArgumentsAccordingToParameters(
                $arguments,
                $method->getParameters()
            )
        );
    }
}
