<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatSessionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
