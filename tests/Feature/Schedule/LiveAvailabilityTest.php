<?php

use App\Actions\BookSlot;
use App\Actions\CancelBooking;
use App\Actions\CancelSlot;
use App\Events\SlotAvailabilityChanged;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;

beforeEach(function () {
    $this->member = User::factory()->create();
});

describe('announcing changes', function () {
    beforeEach(function () {
        Event::fake([SlotAvailabilityChanged::class]);
    });

    it('announces a booking', function () {
        $slot = Slot::factory()->create();

        app(BookSlot::class)->handle($this->member, $slot);

        Event::assertDispatched(SlotAvailabilityChanged::class, fn ($event) => $event->slotId === $slot->id);
    });

    it('announces nothing when a booking fails', function () {
        $slot = Slot::factory()->cancelled()->create();

        expect(fn () => app(BookSlot::class)->handle($this->member, $slot))->toThrow(BookingException::class);

        Event::assertNotDispatched(SlotAvailabilityChanged::class);
    });

    it('announces a cancelled booking', function () {
        $booking = Booking::factory()->create();

        app(CancelBooking::class)->handle($booking);

        Event::assertDispatched(SlotAvailabilityChanged::class, fn ($event) => $event->slotId === $booking->slot_id);
    });

    it('doesn\'t announce cancelling a booking twice', function () {
        $booking = Booking::factory()->cancelled()->create();

        app(CancelBooking::class)->handle($booking);

        Event::assertNotDispatched(SlotAvailabilityChanged::class);
    });

    it('announces a cancelled class', function () {
        $slot = Slot::factory()->create();

        app(CancelSlot::class)->handle($slot);

        Event::assertDispatched(SlotAvailabilityChanged::class, fn ($event) => $event->slotId === $slot->id);
    });

    it('announces an admin\'s edit', function () {
        $this->actingAs(User::factory()->admin()->create());
        $slot = Slot::factory()->create();

        Livewire::test('pages::admin.slots')
            ->call('edit', $slot->id)
            ->set('form.capacity', 50)
            ->call('save');

        Event::assertDispatched(SlotAvailabilityChanged::class, fn ($event) => $event->slotId === $slot->id);
    });

    it('announces a new series once', function () {
        $this->actingAs(User::factory()->admin()->create());
        $start = now()->addDays(2)->startOfHour();

        Livewire::test('pages::admin.slots')
            ->call('create')
            ->set('form.class_type_id', ClassType::factory()->create()->id)
            ->set('form.trainer_id', Trainer::factory()->create()->id)
            ->set('form.starts_at', $start->format('Y-m-d\TH:i'))
            ->set('form.ends_at', $start->addHour()->format('Y-m-d\TH:i'))
            ->set('form.capacity', 5)
            ->set('form.repeat_until', $start->addWeeks(3)->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        expect(Slot::query()->count())->toBe(4);
        Event::assertDispatchedTimes(SlotAvailabilityChanged::class, 1);
    });

    it('waits for the transaction to commit', function () {
        $slot = Slot::factory()->create();

        DB::transaction(function () use ($slot) {
            app(BookSlot::class)->handle($this->member, $slot);

            Event::assertNotDispatched(SlotAvailabilityChanged::class);
        });

        Event::assertDispatched(SlotAvailabilityChanged::class);
    });

    it('announces nothing if the transaction rolls back', function () {
        $slot = Slot::factory()->create();

        try {
            DB::transaction(function () use ($slot) {
                app(BookSlot::class)->handle($this->member, $slot);

                throw new RuntimeException('Something later failed.');
            });
        } catch (RuntimeException) {
            //
        }

        Event::assertNotDispatched(SlotAvailabilityChanged::class);

        expect($slot->bookings()->count())->toBe(0);
    });
});

describe('the broadcast', function () {
    it('uses the public schedule channel', function () {
        $channels = (new SlotAvailabilityChanged(1))->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('schedule');
    });

    it('sends only the slot ID', function () {
        expect((new SlotAvailabilityChanged(42))->broadcastWith())->toBe(['slotId' => 42]);
    });
});

describe('when the WebSocket server is down', function () {
    beforeEach(function () {
        Broadcast::extend('failing', fn () => new class extends Broadcaster
        {
            public function auth($request): mixed
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result): mixed
            {
                return null;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('Connection refused');
            }
        });

        config([
            'broadcasting.default' => 'failing',
            'broadcasting.connections.failing' => ['driver' => 'failing'],
        ]);

        Exceptions::fake();
    });

    it('still books the class, and reports the failure', function () {
        $slot = Slot::factory()->create();

        $booking = app(BookSlot::class)->handle($this->member, $slot);

        expect($booking->fresh()->isActive())->toBeTrue();
        Exceptions::assertReported(BroadcastException::class);
    });

    it('still cancels a booking', function () {
        $booking = Booking::factory()->create();

        app(CancelBooking::class)->handle($booking);

        expect($booking->fresh()->isActive())->toBeFalse();
        Exceptions::assertReported(BroadcastException::class);
    });

    it('shows a member success through the calendar', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->create();

        Livewire::test('pages::schedule')
            ->call('book', $slot->id)
            ->assertSee('data-state="booked"', false);
    });
});

describe('the calendar', function () {
    it('updates its counts when an event arrives', function () {
        $slot = Slot::factory()->capacity(5)->create();
        $page = Livewire::test('pages::schedule')->assertSee('5 spots left');

        Booking::factory()->create(['slot_id' => $slot->id]);

        $page->dispatch('echo:schedule,SlotAvailabilityChanged', ['slotId' => $slot->id])
            ->assertSee('4 spots left');
    });

    it('stops offering a class that fills up', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->capacity(1)->create();
        $page = Livewire::test('pages::schedule')->assertSee('data-state="few_left"', false);

        Booking::factory()->create(['slot_id' => $slot->id]);

        $page->dispatch('echo:schedule,SlotAvailabilityChanged', ['slotId' => $slot->id])
            ->assertSee('data-state="full"', false)
            ->assertDontSee('wire:click="book', false);
    });

    it('keeps the viewer\'s week and filters', function () {
        $page = Livewire::test('pages::schedule')->call('nextWeek');
        $week = $page->get('week');

        $page->dispatch('echo:schedule,SlotAvailabilityChanged', ['slotId' => 1])
            ->assertSet('week', $week);
    });
});
