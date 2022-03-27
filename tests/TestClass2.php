<?php

declare(strict_types=1);

namespace Tests;

final class TestClass2
{
    public function getValue(int $power): int
    {
        return 10 * $power;
    }
}
