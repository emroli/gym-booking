<?php

use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->member = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

describe('cancelling a booking', function () {
    beforeEach(function () {
        $this->freezeSecond();
        config(['booking.cancellation_cutoff_hours' => 2]);
    });

    it('lets the owner cancel exactly at the cutoff', function () {
        $slot = Slot::factory()->at(now()->addHours(2))->create();
        $booking = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $slot->id]);

        expect($this->member->can('cancel', $booking))->toBeTrue();
    });

    it('stops the owner one second after the cutoff, and says why', function () {
        $slot = Slot::factory()->at(now()->addHours(2)->subSecond())->create();
        $booking = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $slot->id]);

        $response = Gate::forUser($this->member)->inspect('cancel', $booking);

        expect($response->denied())->toBeTrue()
            ->and($response->message())->toBe('Bookings can only be cancelled up to 2 hours before the class.');
    });

    it('stops a member cancelling someone else\'s booking', function () {
        $booking = Booking::factory()->create();

        $response = Gate::forUser($this->member)->inspect('cancel', $booking);

        expect($response->denied())->toBeTrue()
            ->and($response->message())->toBe('You can only cancel your own bookings.');
    });

    it('lets an admin cancel anyone\'s booking after the cutoff', function () {
        $slot = Slot::factory()->at(now()->addMinutes(5))->create();
        $booking = Booking::factory()->create(['slot_id' => $slot->id]);

        expect($this->admin->can('cancel', $booking))->toBeTrue();
    });

    it('stops anyone cancelling a booking that is already cancelled', function (string $who) {
        $booking = Booking::factory()->cancelled()->create(['user_id' => $this->member->id]);

        expect($this->{$who}->can('cancel', $booking))->toBeFalse();
    })->with(['member', 'admin']);
});

describe('managing the schedule', function () {
    it('limits admin abilities to admins', function (string $ability, Closure $target) {
        expect($this->member->can($ability, $target()))->toBeFalse()
            ->and($this->admin->can($ability, $target()))->toBeTrue();
    })->with([
        'view slots' => ['viewAny', fn () => Slot::class],
        'create slots' => ['create', fn () => Slot::class],
        'update a slot' => ['update', fn () => Slot::factory()->create()],
        'cancel a slot' => ['cancel', fn () => Slot::factory()->create()],
        'view attendees' => ['viewAttendees', fn () => Slot::factory()->create()],
        'view class types' => ['viewAny', fn () => ClassType::class],
        'create class types' => ['create', fn () => ClassType::class],
        'update a class type' => ['update', fn () => ClassType::factory()->create()],
        'delete a class type' => ['delete', fn () => ClassType::factory()->create()],
        'view trainers' => ['viewAny', fn () => Trainer::class],
        'create trainers' => ['create', fn () => Trainer::class],
        'update a trainer' => ['update', fn () => Trainer::factory()->create()],
        'delete a trainer' => ['delete', fn () => Trainer::factory()->create()],
    ]);

    it('stops admins changing a finished or cancelled slot', function (Closure $slot, string $message) {
        $response = Gate::forUser($this->admin)->inspect('update', $slot());

        expect($response->denied())->toBeTrue()
            ->and($response->message())->toBe($message);
    })->with([
        'finished' => [fn () => Slot::factory()->at(now()->subHours(2))->create(), 'This class has already started.'],
        'cancelled' => [fn () => Slot::factory()->cancelled()->create(), 'This class has been cancelled.'],
    ]);

    it('stops deleting a class type that has slots', function () {
        $classType = Slot::factory()->create()->classType;

        $response = Gate::forUser($this->admin)->inspect('delete', $classType);

        expect($response->denied())->toBeTrue()
            ->and($response->message())->toContain('still has slots');
    });

    it('stops deleting a trainer who has slots', function () {
        $trainer = Slot::factory()->create()->trainer;

        $response = Gate::forUser($this->admin)->inspect('delete', $trainer);

        expect($response->denied())->toBeTrue()
            ->and($response->message())->toContain('still has slots');
    });
});
