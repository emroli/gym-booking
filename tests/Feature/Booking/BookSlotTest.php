<?php

use App\Actions\BookSlot;
use App\Actions\CancelBooking;
use App\Enums\BookingFailure;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function bookingFailure(User $user, Slot $slot): ?BookingFailure
{
    try {
        app(BookSlot::class)->handle($user, $slot);
    } catch (BookingException $exception) {
        return $exception->failure;
    }

    return null;
}

beforeEach(function () {
    $this->freezeSecond();
    $this->member = User::factory()->create();
    $this->start = now()->addDays(2)->startOfHour();
});

describe('capacity', function () {
    it('lets a member take the last spot', function () {
        $slot = Slot::factory()->capacity(1)->create();

        $booking = app(BookSlot::class)->handle($this->member, $slot);

        expect($booking->isActive())->toBeTrue()
            ->and($slot->spots_left)->toBe(0);
    });

    it('rejects a booking once the slot is full', function () {
        $slot = Slot::factory()->capacity(1)->create();
        Booking::factory()->create(['slot_id' => $slot->id]);

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::SlotFull)
            ->and($slot->bookings()->count())->toBe(1);
    });

    it('frees a spot when a booking is cancelled', function () {
        $slot = Slot::factory()->capacity(1)->create();
        $booking = Booking::factory()->create(['slot_id' => $slot->id]);

        app(CancelBooking::class)->handle($booking);

        expect(bookingFailure($this->member, $slot))->toBeNull();
    });

    it('checks capacity against the database, not a stale model', function () {
        $slot = Slot::factory()->capacity(1)->create();
        $stale = Slot::query()->withAvailability()->findOrFail($slot->id);
        Booking::factory()->create(['slot_id' => $slot->id]);

        expect($stale->spots_left)->toBe(1)
            ->and(bookingFailure($this->member, $stale))->toBe(BookingFailure::SlotFull);
    });
});

describe('double booking', function () {
    it('rejects booking the same slot twice', function () {
        $slot = Slot::factory()->create();
        app(BookSlot::class)->handle($this->member, $slot);

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::AlreadyBooked)
            ->and($slot->bookings()->count())->toBe(1);
    });

    it('rejects a slot that overlaps an existing booking', function (int $offset, int $minutes) {
        $booked = Slot::factory()->at($this->start)->create();
        Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $booked->id]);

        $other = Slot::factory()->at($this->start->addMinutes($offset), $minutes)->create();

        expect(bookingFailure($this->member, $other))->toBe(BookingFailure::Overlapping);
    })->with([
        'starts during' => [30, 60],
        'ends during' => [-30, 60],
        'inside' => [15, 15],
        'surrounds' => [-15, 120],
        'same time' => [0, 60],
    ]);

    it('allows back-to-back classes', function (int $offset) {
        $booked = Slot::factory()->at($this->start)->create();
        Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $booked->id]);

        $other = Slot::factory()->at($this->start->addMinutes($offset))->create();

        expect(bookingFailure($this->member, $other))->toBeNull();
    })->with(['straight after' => 60, 'straight before' => -60]);

    it('ignores overlaps with cancelled bookings', function () {
        $booked = Slot::factory()->at($this->start)->create();
        Booking::factory()->cancelled()->create(['user_id' => $this->member->id, 'slot_id' => $booked->id]);

        $other = Slot::factory()->at($this->start)->create();

        expect(bookingFailure($this->member, $other))->toBeNull();
    });

    it('ignores overlaps with cancelled classes', function () {
        $booked = Slot::factory()->at($this->start)->cancelled()->create();
        Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $booked->id]);

        $other = Slot::factory()->at($this->start)->create();

        expect(bookingFailure($this->member, $other))->toBeNull();
    });

    it('ignores other members bookings', function () {
        $booked = Slot::factory()->at($this->start)->create();
        Booking::factory()->create(['slot_id' => $booked->id]);

        $other = Slot::factory()->at($this->start)->create();

        expect(bookingFailure($this->member, $other))->toBeNull();
    });

    it('turns a unique index violation into the normal message', function () {
        $slot = Slot::factory()->create();

        // Another request inserts its row after our checks have passed.
        Booking::creating(function () use ($slot) {
            Booking::flushEventListeners();

            DB::table('bookings')->insert([
                'user_id' => $this->member->id,
                'slot_id' => $slot->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::AlreadyBooked);
    });
});

describe('rebooking', function () {
    it('reactivates the cancelled row instead of adding another', function () {
        $slot = Slot::factory()->create();
        $first = app(BookSlot::class)->handle($this->member, $slot);
        app(CancelBooking::class)->handle($first);

        $second = app(BookSlot::class)->handle($this->member, $slot);

        expect($second->id)->toBe($first->id)
            ->and($second->isActive())->toBeTrue()
            ->and($slot->bookings()->count())->toBe(1);
    });

    it('still needs a free spot', function () {
        $slot = Slot::factory()->capacity(1)->create();
        $first = app(BookSlot::class)->handle($this->member, $slot);
        app(CancelBooking::class)->handle($first);
        Booking::factory()->create(['slot_id' => $slot->id]);

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::SlotFull)
            ->and($first->fresh()->isActive())->toBeFalse();
    });
});

describe('timing', function () {
    it('rejects a slot that has started', function (int $minutes) {
        $slot = Slot::factory()->at(now()->addMinutes($minutes))->create();

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::SlotStarted);
    })->with(['starting right now' => 0, 'in progress' => -10]);

    it('allows a slot starting a moment from now', function () {
        $slot = Slot::factory()->at(now()->addSecond())->create();

        expect(bookingFailure($this->member, $slot))->toBeNull();
    });

    it('allows a slot exactly at the end of the booking window', function () {
        config(['booking.window_days' => 14]);
        $slot = Slot::factory()->at(now()->addDays(14))->create();

        expect(bookingFailure($this->member, $slot))->toBeNull();
    });

    it('rejects a slot just past the booking window', function () {
        config(['booking.window_days' => 14]);
        $slot = Slot::factory()->at(now()->addDays(14)->addSecond())->create();

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::OutsideWindow);
    });

    it('rejects a cancelled slot', function () {
        $slot = Slot::factory()->cancelled()->create();

        expect(bookingFailure($this->member, $slot))->toBe(BookingFailure::SlotCancelled);
    });
});

it('saves nothing when a booking fails', function () {
    $slot = Slot::factory()->capacity(1)->create();
    Booking::factory()->create(['slot_id' => $slot->id]);

    bookingFailure($this->member, $slot);

    expect($this->member->bookings()->count())->toBe(0);
});

it('uses a clear message for each failure', function (BookingFailure $failure, string $expected) {
    config(['booking.window_days' => 14]);

    expect((new BookingException($failure))->getMessage())->toBe($expected);
})->with([
    [BookingFailure::SlotFull, 'Sorry, this class is full.'],
    [BookingFailure::AlreadyBooked, 'You have already booked this class.'],
    [BookingFailure::Overlapping, 'You have another class booked at this time.'],
    [BookingFailure::OutsideWindow, 'Classes can be booked up to 14 days ahead.'],
    [BookingFailure::SlotStarted, 'This class has already started.'],
    [BookingFailure::SlotCancelled, 'This class has been cancelled.'],
]);

it('keeps the database in the immediate transaction mode that prevents overbooking', function () {
    expect(config('database.connections.sqlite.transaction_mode'))->toBe('IMMEDIATE');
});
