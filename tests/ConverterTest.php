<?php

declare(strict_types=1);

namespace Tests;

use EugeneErg\ClassCreator\Converter;
use EugeneErg\ClassCreator\DependencyInjector;
use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
    public function testRegisterIntToArray(): void
    {
        $converter = (new DependencyInjector)->create(Converter::class);
        $converter->register(function (int $value): array {
            return array_fill(0, $value, null);
        });
        $this->assertEquals([null, null, null], $converter->convert(['array'], 3));
    }

    public function testRegisterArrayToInt(): void
    {
        $converter = $this->getConverter();
        $converter->register(function (array $value): int {
            return count($value);
        });
        $this->assertEquals(3, $converter->convert(['integer'], [null, null, null]));
    }

    public function testRegisterStringToInt(): void
    {
        $converter = $this->getConverter();
        $converter->register(function (string $value): int {
            return strlen($value);
        });
        $this->assertEquals(9, $converter->convert(['integer'], 'rgvtrgvrt'));
    }

    public function testRegisterIntToString(): void
    {
        $converter = (new DependencyInjector)->create(Converter::class);
        $converter->register(function (int $value): string {
            return str_repeat(' ', $value);
        });
        $this->assertEquals('    ', $converter->convert(['string'], 4));
    }

    public function testRegisterNullToClass(): void
    {
        $converter = (new DependencyInjector)->create(Converter::class);
        $converter->register(function (): TestClass2 {
            return new TestClass2();
        });
        $this->assertEquals(new TestClass2(), $converter->convert([TestClass2::class]));
    }

    public function testRegisterNullToClass2(): void
    {
        $converter = (new DependencyInjector)->create(Converter::class);
        $converter->register(function () use ($converter): TestClass {
            return new TestClass(
                $converter->convert([TestClass2::class]),
                1
            );
        });
        $this->assertEquals(new TestClass(new TestClass2(), 1), $converter->convert([TestClass::class]));
    }

    public function testCreateClass(): void
    {
        $actual = (new DependencyInjector)->create(TestClass3::class, ['value' => 999]);

        $this->assertEquals(new TestClass3(999), $actual);
    }

    private function getConverter(): Converter
    {
        /** @var Converter $result */
        $result = (new DependencyInjector)->create(Converter::class);

        return $result;
    }
}
