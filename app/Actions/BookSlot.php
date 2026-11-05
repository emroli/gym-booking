<?php

namespace App\Actions;

use App\Enums\BookingFailure;
use App\Events\SlotAvailabilityChanged;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class BookSlot
{
    /**
     * Every check runs inside one write transaction, so nothing can change
     * between checking for a free spot and taking it.
     *
     * @throws BookingException
     */
    public function handle(User $user, Slot $slot): Booking
    {
        try {
            return DB::transaction(function () use ($user, $slot): Booking {
                // SQLite ignores lockForUpdate and relies on its immediate
                // transactions instead. Other databases need the row lock.
                $slot = Slot::query()->lockForUpdate()->findOrFail($slot->id);

                $this->ensureCanBook($user, $slot);

                $booking = Booking::query()->firstOrNew(['user_id' => $user->id, 'slot_id' => $slot->id]);
                $booking->cancelled_at = null;
                $booking->save();

                SlotAvailabilityChanged::announce($slot->id);

                return $booking;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingException(BookingFailure::AlreadyBooked);
        }
    }

    /**
     * @throws BookingException
     */
    private function ensureCanBook(User $user, Slot $slot): void
    {
        $failure = match (true) {
            $slot->isCancelled() => BookingFailure::SlotCancelled,
            $slot->hasStarted() => BookingFailure::SlotStarted,
            ! $slot->isWithinBookingWindow() => BookingFailure::OutsideWindow,
            $this->hasBooked($user, $slot) => BookingFailure::AlreadyBooked,
            $this->hasOverlappingBooking($user, $slot) => BookingFailure::Overlapping,
            $slot->bookings()->active()->count() >= $slot->capacity => BookingFailure::SlotFull,
            default => null,
        };

        if ($failure !== null) {
            throw new BookingException($failure);
        }
    }

    private function hasBooked(User $user, Slot $slot): bool
    {
        return $slot->bookings()->active()->where('user_id', $user->id)->exists();
    }

    /**
     * Bookings on cancelled classes don't count, because those classes
     * aren't happening.
     */
    private function hasOverlappingBooking(User $user, Slot $slot): bool
    {
        return $user->bookings()
            ->active()
            ->whereHas('slot', fn ($query) => $query
                ->whereNull('cancelled_at')
                ->overlapping($slot->starts_at, $slot->ends_at))
            ->exists();
    }
}
