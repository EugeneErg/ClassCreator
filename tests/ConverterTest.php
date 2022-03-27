<?php

declare(strict_types=1);

namespace Tests;

use EugeneErg\ClassCreator\Converter;
use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
    public function testRegisterIntToArray(): void
    {
        Converter::instance()->register(function (int $value): array {
            return array_fill(0, $value, null);
        });
        $this->assertEquals([null, null, null], Converter::instance()->convert(['array'], 3));
    }

    public function testRegisterArrayToInt(): void
    {
        Converter::instance()->register(function (array $value): int {
            return count($value);
        });
        $this->assertEquals(3, Converter::instance()->convert(['integer'], [null, null, null]));
    }

    public function testRegisterStringToInt(): void
    {
        Converter::instance()->register(function (string $value): int {
            return strlen($value);
        });
        $this->assertEquals(9, Converter::instance()->convert(['integer'], 'rgvtrgvrt'));
    }

    public function testRegisterIntToString(): void
    {
        Converter::instance()->register(function (int $value): string {
            return str_repeat(' ', $value);
        });
        $this->assertEquals('    ', Converter::instance()->convert(['string'], 4));
    }

    public function testRegisterNullToClass(): void
    {
        Converter::instance()->register(function (): TestClass2 {
            return new TestClass2();
        });
        $this->assertEquals(new TestClass2(), Converter::instance()->convert([TestClass2::class]));
    }

    public function testRegisterNullToClass2(): void
    {
        Converter::instance()->register(function (): TestClass {
            return new TestClass(
                Converter::instance()->convert([TestClass2::class]),
                1
            );
        });
        $this->assertEquals(new TestClass(new TestClass2(), 1), Converter::instance()->convert([TestClass::class]));
    }
}
