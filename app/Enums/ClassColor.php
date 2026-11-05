<?php

namespace App\Enums;

enum ClassColor: string
{
    case Sky = 'sky';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Violet = 'violet';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Full class names, so Tailwind can find them when it scans this file. The
     * dark variant is needed to win over a card's dark:border-zinc-700.
     */
    public function accentClass(): string
    {
        return match ($this) {
            self::Sky => 'border-l-sky-500 dark:border-l-sky-500',
            self::Emerald => 'border-l-emerald-500 dark:border-l-emerald-500',
            self::Amber => 'border-l-amber-500 dark:border-l-amber-500',
            self::Rose => 'border-l-rose-500 dark:border-l-rose-500',
            self::Violet => 'border-l-violet-500 dark:border-l-violet-500',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Sky => 'bg-sky-500',
            self::Emerald => 'bg-emerald-500',
            self::Amber => 'bg-amber-500',
            self::Rose => 'bg-rose-500',
            self::Violet => 'bg-violet-500',
        };
    }
}
