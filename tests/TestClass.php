<?php

declare(strict_types=1);

namespace Tests;

final class TestClass
{
    private TestClass2 $class;
    private int $value;

    public function __construct(TestClass2 $class, int $value)
    {
        $this->class = $class;
        $this->value = $value;
    }
}
