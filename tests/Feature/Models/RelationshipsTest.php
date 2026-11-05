<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

it('connects a slot to its class type, trainer and attendees', function () {
    $classType = ClassType::factory()->create();
    $trainer = Trainer::factory()->create();
    $slot = Slot::factory()->for($classType)->for($trainer)->create();
    $member = User::factory()->create();
    Booking::factory()->create(['user_id' => $member->id, 'slot_id' => $slot->id]);

    expect($slot->classType->is($classType))->toBeTrue()
        ->and($slot->trainer->is($trainer))->toBeTrue()
        ->and($slot->attendees->pluck('id')->all())->toBe([$member->id])
        ->and($member->bookedSlots->pluck('id')->all())->toBe([$slot->id])
        ->and($classType->slots)->toHaveCount(1)
        ->and($trainer->slots)->toHaveCount(1);
});

it('leaves cancelled bookings out of attendees and booked slots', function () {
    $booking = Booking::factory()->cancelled()->create();

    expect($booking->slot->attendees)->toBeEmpty()
        ->and($booking->user->bookedSlots)->toBeEmpty()
        ->and($booking->user->bookings)->toHaveCount(1);
});

it('counts only active bookings towards spots left', function () {
    $slot = Slot::factory()->capacity(3)->create();
    Booking::factory()->create(['slot_id' => $slot->id]);
    Booking::factory()->cancelled()->create(['slot_id' => $slot->id]);

    $loaded = Slot::query()->withAvailability()->findOrFail($slot->id);

    expect($loaded->active_bookings_count)->toBe(1)
        ->and($loaded->spots_left)->toBe(2)
        ->and($slot->spots_left)->toBe(2);
});

it('blocks a second booking row for the same member and slot at the database level', function () {
    $booking = Booking::factory()->create();

    Booking::factory()->create(['user_id' => $booking->user_id, 'slot_id' => $booking->slot_id]);
})->throws(QueryException::class);

it('treats touching slots as not overlapping', function () {
    $start = now()->addDays(2)->startOfHour();
    $slot = Slot::factory()->at($start)->create();

    $overlaps = fn ($from, $to) => Slot::query()->overlapping($from, $to)->whereKey($slot->id)->exists();

    expect($overlaps($start->addMinutes(30), $start->addMinutes(90)))->toBeTrue()
        ->and($overlaps($start->subMinutes(30), $start->addMinutes(30)))->toBeTrue()
        ->and($overlaps($start->addMinutes(15), $start->addMinutes(30)))->toBeTrue()
        ->and($overlaps($start->addHour(), $start->addHours(2)))->toBeFalse()
        ->and($overlaps($start->subHour(), $start))->toBeFalse();
});

it('does not let a mass-assigned role create an admin', function () {
    $user = new User(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'role' => Role::Admin->value]);

    expect($user->role)->toBe(Role::Member)
        ->and($user->isAdmin())->toBeFalse();
});

it('seeds demo data without breaking capacity or overlap rules', function () {
    $this->seed(DatabaseSeeder::class);

    $overbooked = Slot::query()->withAvailability()->get()
        ->filter(fn (Slot $slot): bool => $slot->active_bookings_count > $slot->capacity);

    expect($overbooked)->toBeEmpty()
        ->and(User::query()->where('email', 'admin@example.com')->firstOrFail()->isAdmin())->toBeTrue()
        ->and(User::query()->where('email', 'member@example.com')->firstOrFail()->bookedSlots)->toHaveCount(2);
});

it('refuses to seed demo logins in production', function () {
    app()->detectEnvironment(fn () => 'production');

    (new DatabaseSeeder)->run();
})->throws(RuntimeException::class, 'must not run in production');
