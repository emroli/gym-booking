<?php

namespace App\Actions;

use App\Events\SlotAvailabilityChanged;
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

            SlotAvailabilityChanged::announce($booking->slot_id);
        }

        return $booking;
    }
}
