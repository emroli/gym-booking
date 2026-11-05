<?php

namespace App\Enums;

use App\Models\Slot;

/**
 * How a slot looks to one viewer on the calendar.
 */
enum SlotState: string
{
    case Open = 'open';
    case FewLeft = 'few_left';
    case Full = 'full';
    case Booked = 'booked';
    case NotOpenYet = 'not_open_yet';
    case Past = 'past';
    case Cancelled = 'cancelled';

    public static function for(Slot $slot, bool $bookedByViewer = false): self
    {
        return match (true) {
            $slot->isCancelled() => self::Cancelled,
            $slot->hasStarted() => self::Past,
            $bookedByViewer => self::Booked,
            ! $slot->isWithinBookingWindow() => self::NotOpenYet,
            $slot->spots_left === 0 => self::Full,
            $slot->spots_left <= config()->integer('booking.few_spots_threshold') => self::FewLeft,
            default => self::Open,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::FewLeft => 'Few left',
            self::Full => 'Full',
            self::Booked => 'Booked',
            self::NotOpenYet => 'Not open yet',
            self::Past => 'Finished',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * A Flux badge color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::FewLeft => 'amber',
            self::Full => 'red',
            self::Booked => 'blue',
            self::NotOpenYet, self::Past, self::Cancelled => 'zinc',
        };
    }

    public function canBeBooked(): bool
    {
        return match ($this) {
            self::Open, self::FewLeft => true,
            default => false,
        };
    }
}
