<?php

declare(strict_types=1);

namespace Tests;

final class TestClass3
{
    private int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }
}
