<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessStatus: string
{
    case Pending = 'pending';
    case Scraping = 'scraping';
    case Summarizing = 'summarizing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Scraping => 'Scraping Website',
            self::Summarizing => 'Synthesizing Knowledge',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed], true);
    }
}
