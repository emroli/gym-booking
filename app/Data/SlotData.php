<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class SlotData
{
    public function __construct(
        public int $classTypeId,
        public int $trainerId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $capacity,
    ) {}

    public function minutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }
}
