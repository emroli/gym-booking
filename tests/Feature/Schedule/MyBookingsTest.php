<?php

use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeSecond();
    $this->member = User::factory()->create();
    $this->actingAs($this->member);
});

it('needs a login', function () {
    auth()->logout();

    $this->get(route('my-bookings'))->assertRedirect(route('login'));
});

it('splits upcoming bookings from history, soonest first', function () {
    $later = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->at(now()->addDays(5))]);
    $sooner = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->at(now()->addDays(3))]);
    $finished = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->at(now()->subDay())]);
    $cancelled = Booking::factory()->cancelled()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->at(now()->addDays(4))]);

    $page = Livewire::test('pages::my-bookings')->instance();

    expect($page->upcoming->pluck('id')->all())->toBe([$sooner->id, $later->id])
        ->and($page->history->pluck('id')->all())->toBe([$cancelled->id, $finished->id]);
});

it('only shows the member\'s own bookings', function () {
    $mine = ClassType::factory()->create(['name' => 'Mine']);
    $theirs = ClassType::factory()->create(['name' => 'Theirs']);
    Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->for($mine)]);
    Booking::factory()->create(['slot_id' => Slot::factory()->for($theirs)]);

    Livewire::test('pages::my-bookings')->assertSee('Mine')->assertDontSee('Theirs');
});

it('tells the member when a booked class is cancelled', function () {
    Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => Slot::factory()->cancelled()]);

    Livewire::test('pages::my-bookings')
        ->assertSee('Class cancelled')
        ->assertDontSee('wire:click="cancel', false);
});

it('cancels a booking before the cutoff', function () {
    $booking = Booking::factory()->create(['user_id' => $this->member->id]);

    Livewire::test('pages::my-bookings')->call('cancel', $booking->id);

    expect($booking->fresh()->isActive())->toBeFalse();
});

it('won\'t cancel after the cutoff', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->member->id,
        'slot_id' => Slot::factory()->at(now()->addHour()),
    ]);

    Livewire::test('pages::my-bookings')
        ->assertSee('Too late to cancel')
        ->call('cancel', $booking->id);

    expect($booking->fresh()->isActive())->toBeTrue();
});

it('won\'t cancel someone else\'s booking', function () {
    $booking = Booking::factory()->create();

    Livewire::test('pages::my-bookings')->call('cancel', $booking->id);

    expect($booking->fresh()->isActive())->toBeTrue();
});

it('shows a friendly message when there is nothing booked', function () {
    Livewire::test('pages::my-bookings')->assertSee('Nothing booked yet');
});
