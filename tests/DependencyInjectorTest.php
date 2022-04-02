<?php

declare(strict_types=1);

namespace Tests;

use EugeneErg\ClassCreator\Converter;
use EugeneErg\ClassCreator\DependencyInjector;
use PHPUnit\Framework\TestCase;

final class DependencyInjectorTest extends TestCase
{
    public function testCreate(): void
    {
        $dependencyInjector = new DependencyInjector(new Converter());
        $actual = $dependencyInjector->create(TestClass::class, ['value' => 2]);
        $this->assertInstanceOf(TestClass::class, $actual);
    }

    public function testCall(): void
    {
        $dependencyInjector = new DependencyInjector(new Converter());
        $actual = $dependencyInjector->call([TestClass2::class, 'getValue'], ['power' => 2]);
        $this->assertEquals(20, $actual);
    }
}
