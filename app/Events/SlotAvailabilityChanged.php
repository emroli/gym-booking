<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * Tells every open calendar to re-read availability from the database. It goes
 * out on a public channel, so it carries a slot ID and never any member data.
 */
final class SlotAvailabilityChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly int $slotId) {}

    /**
     * Waits for the commit, so nobody reloads before the change is visible. A
     * broadcast failure is only logged: the booking has already succeeded, and
     * the worst outcome is that open calendars miss a live update.
     */
    public static function announce(int $slotId): void
    {
        DB::afterCommit(fn () => rescue(fn () => self::dispatch($slotId)));
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('schedule')];
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['slotId' => $this->slotId];
    }
}
