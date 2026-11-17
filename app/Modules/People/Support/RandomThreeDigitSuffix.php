<?php

namespace App\Modules\People\Support;

class RandomThreeDigitSuffix
{
    public function generate(): string
    {
        return (string) random_int(100, 999);
    }
}
