<?php

use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Livewire\Livewire;

dataset('admin pages', [
    'slots' => fn () => route('admin.slots'),
    'attendees' => fn () => route('admin.slots.attendees', Slot::factory()->create()),
    'class types' => fn () => route('admin.class-types'),
    'trainers' => fn () => route('admin.trainers'),
]);

it('sends guests to the login page', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('admin pages');

it('blocks members', function (string $url) {
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with('admin pages');

it('lets admins in', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with('admin pages');

it('only shows admin links to admins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertDontSee(route('admin.slots'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('profile.edit'))
        ->assertSee(route('admin.slots'));
});

/*
 * Livewire actions are their own endpoints, so each one has to check for itself.
 * These open the page as an admin, then call the action as a member.
 */
describe('when someone loses admin access with a page still open', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->member = User::factory()->create();
        $this->actingAs($this->admin);
    });

    it('blocks actions on the slots page', function (string $method, Closure $arguments) {
        $page = Livewire::test('pages::admin.slots');

        $this->actingAs($this->member);

        $page->call($method, ...$arguments())->assertForbidden();
    })->with([
        'create' => ['create', fn () => []],
        'edit' => ['edit', fn () => [Slot::factory()->create()->id]],
        'cancel' => ['cancel', fn () => [Slot::factory()->create()->id]],
        'save' => ['save', fn () => []],
    ]);

    it('does not cancel the slot', function () {
        $slot = Slot::factory()->create();
        $page = Livewire::test('pages::admin.slots');

        $this->actingAs($this->member);
        $page->call('cancel', $slot->id);

        expect($slot->fresh()->isCancelled())->toBeFalse();
    });

    it('blocks actions on the class types page', function (string $method, Closure $arguments) {
        $page = Livewire::test('pages::admin.class-types');

        $this->actingAs($this->member);

        $page->call($method, ...$arguments())->assertForbidden();
    })->with([
        'create' => ['create', fn () => []],
        'edit' => ['edit', fn () => [ClassType::factory()->create()->id]],
        'delete' => ['delete', fn () => [ClassType::factory()->create()->id]],
        'save' => ['save', fn () => []],
    ]);

    it('blocks actions on the trainers page', function (string $method, Closure $arguments) {
        $page = Livewire::test('pages::admin.trainers');

        $this->actingAs($this->member);

        $page->call($method, ...$arguments())->assertForbidden();
    })->with([
        'create' => ['create', fn () => []],
        'edit' => ['edit', fn () => [Trainer::factory()->create()->id]],
        'delete' => ['delete', fn () => [Trainer::factory()->create()->id]],
        'save' => ['save', fn () => []],
    ]);

    it('blocks cancelling a booking from the attendees page', function () {
        $booking = Booking::factory()->create();
        $page = Livewire::test('pages::admin.slot-attendees', ['slot' => $booking->slot]);

        $this->actingAs($this->member);

        $page->call('cancelBooking', $booking->id)->assertForbidden();

        expect($booking->fresh()->isActive())->toBeTrue();
    });
});
