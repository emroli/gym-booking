<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    public function cancel(User $user, Booking $booking): Response
    {
        if (! $booking->isActive()) {
            return Response::deny('This booking is already cancelled.');
        }

        if ($user->isAdmin()) {
            return Response::allow();
        }

        if ($booking->user_id !== $user->id) {
            return Response::deny('You can only cancel your own bookings.');
        }

        $hours = config()->integer('booking.cancellation_cutoff_hours');
        $deadline = $booking->slot->starts_at->subHours($hours);

        return now()->lessThanOrEqualTo($deadline)
            ? Response::allow()
            : Response::deny(sprintf('Bookings can only be cancelled up to %d hours before the class.', $hours));
    }
}
