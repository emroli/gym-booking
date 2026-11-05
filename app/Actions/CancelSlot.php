<?php

namespace App\Actions;

use App\Events\SlotAvailabilityChanged;
use App\Models\Slot;

final readonly class CancelSlot
{
    /**
     * Bookings are kept, so members can still see the class was cancelled.
     */
    public function handle(Slot $slot): Slot
    {
        if (! $slot->isCancelled()) {
            $slot->cancelled_at = now();
            $slot->save();

            SlotAvailabilityChanged::announce($slot->id);
        }

        return $slot;
    }
}
