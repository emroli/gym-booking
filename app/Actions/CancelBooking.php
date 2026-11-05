<?php

namespace App\Actions;

use App\Models\Booking;

final readonly class CancelBooking
{
    /**
     * Who may cancel, and until when, is decided by BookingPolicy.
     */
    public function handle(Booking $booking): Booking
    {
        if ($booking->isActive()) {
            $booking->cancelled_at = now();
            $booking->save();
        }

        return $booking;
    }
}
