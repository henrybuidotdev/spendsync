<?php

namespace App\Enums;

enum Locale: string
{
    case Vi = 'vi';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Vi => 'Tiếng Việt',
            self::En => 'English',
        };
    }
}
