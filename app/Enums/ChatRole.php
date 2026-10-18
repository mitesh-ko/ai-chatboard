<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';

    public function isHuman(): bool
    {
        return $this === self::User;
    }
}
