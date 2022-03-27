<?php

declare(strict_types=1);

namespace Tests;

use EugeneErg\ClassCreator\DependencyInjector;
use PHPUnit\Framework\TestCase;

final class DependencyInjectorTest extends TestCase
{
    public function testCreate(): void
    {
        $actual = DependencyInjector::instance()->create(TestClass::class, ['value' => 2]);
        $this->assertInstanceOf(TestClass::class, $actual);
    }

    public function testCall(): void
    {
        $actual = DependencyInjector::instance()->call([TestClass2::class, 'getValue'], ['power' => 2]);
        $this->assertEquals(20, $actual);
    }
}
