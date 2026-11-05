<?php

namespace App\Enums;

enum BookingFailure: string
{
    case SlotCancelled = 'slot_cancelled';
    case SlotStarted = 'slot_started';
    case OutsideWindow = 'outside_window';
    case AlreadyBooked = 'already_booked';
    case Overlapping = 'overlapping';
    case SlotFull = 'slot_full';

    public function message(): string
    {
        return match ($this) {
            self::SlotCancelled => 'This class has been cancelled.',
            self::SlotStarted => 'This class has already started.',
            self::OutsideWindow => sprintf('Classes can be booked up to %d days ahead.', config()->integer('booking.window_days')),
            self::AlreadyBooked => 'You have already booked this class.',
            self::Overlapping => 'You have another class booked at this time.',
            self::SlotFull => 'Sorry, this class is full.',
        };
    }
}
