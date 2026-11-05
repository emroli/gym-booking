<?php

use App\Enums\SlotState;
use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    // A Wednesday, so the week runs Mon 2 Nov to Sun 8 Nov.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
    $this->member = User::factory()->create();
});

describe('viewing', function () {
    it('is open to guests', function () {
        $this->get(route('schedule'))->assertOk()->assertSee('Schedule');
    });

    it('asks guests to log in instead of offering to book', function () {
        Slot::factory()->create();

        $this->get(route('schedule'))
            ->assertSee('Log in to book')
            ->assertDontSee('wire:click="book', false);
    });

    it('offers members a book button', function () {
        Slot::factory()->create();

        $this->actingAs($this->member)
            ->get(route('schedule'))
            ->assertSee('wire:click="book', false)
            ->assertDontSee('Log in to book');
    });

    it('shows spots left, not counting cancelled bookings', function () {
        $slot = Slot::factory()->capacity(5)->create();
        Booking::factory(2)->create(['slot_id' => $slot->id]);
        Booking::factory()->cancelled()->create(['slot_id' => $slot->id]);

        Livewire::test('pages::schedule')->assertSee('3 spots left');
    });

    it('uses the singular for one spot', function () {
        Slot::factory()->capacity(1)->create();

        Livewire::test('pages::schedule')->assertSee('1 spot left');
    });

    it('doesn\'t run more queries as classes are added', function () {
        $this->actingAs($this->member);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test('pages::schedule');

            return count(DB::getQueryLog());
        };

        Booking::factory()->create(['user_id' => $this->member->id]);
        $count(); // The first render also compiles views and warms caches.
        $few = $count();

        foreach (range(1, 8) as $hour) {
            $slot = Slot::factory()->at(now()->addDays(2)->startOfDay()->addHours($hour))->create();
            Booking::factory(2)->create(['slot_id' => $slot->id]);
            Booking::factory()->create(['slot_id' => $slot->id, 'user_id' => $this->member->id]);
        }

        expect($count())->toBe($few);
    });
});

describe('the week', function () {
    it('starts on Monday and shows only that week', function () {
        $sunday = Slot::factory()->at(CarbonImmutable::parse('2026-11-08 18:00'))->create();
        $nextMonday = Slot::factory()->at(CarbonImmutable::parse('2026-11-09 07:00'))->create();

        $page = Livewire::test('pages::schedule');

        expect($page->instance()->weekStart->format('Y-m-d'))->toBe('2026-11-02')
            ->and($page->instance()->classesByDay->flatten()->pluck('id')->all())->toBe([$sunday->id])
            ->and($nextMonday->id)->not->toBe($sunday->id);
    });

    it('moves between weeks', function () {
        Livewire::test('pages::schedule')
            ->call('nextWeek')
            ->assertSet('week', '2026-11-09')
            ->call('nextWeek')
            ->assertSet('week', '2026-11-16')
            ->call('previousWeek')
            ->assertSet('week', '2026-11-09')
            ->call('thisWeek')
            ->assertSet('week', '');
    });

    it('reads the week from the URL', function () {
        Livewire::withQueryParams(['week' => '2026-11-18'])
            ->test('pages::schedule')
            ->assertSee('16 Nov – 22 Nov 2026');
    });

    it('falls back to this week for a bad link', function (string $week) {
        Livewire::withQueryParams(['week' => $week])
            ->test('pages::schedule')
            ->assertSee('2 Nov – 8 Nov 2026');
    })->with(['text' => 'next-tuesday', 'wrong format' => '18/11/2026', 'script' => '<script>']);

    it('survives bad filters in a link', function () {
        $this->get(route('schedule', ['class' => 'abc', 'trainer' => '<script>', 'week' => 'x']))->assertOk();
    });
});

describe('filters', function () {
    beforeEach(function () {
        $this->yoga = ClassType::factory()->create(['name' => 'Yoga']);
        $this->spin = ClassType::factory()->create(['name' => 'Spin']);
        $this->sam = Trainer::factory()->create();
        $this->lee = Trainer::factory()->create();

        $this->yogaWithSam = Slot::factory()->for($this->yoga)->for($this->sam)->create();
        $this->spinWithLee = Slot::factory()->for($this->spin)->for($this->lee)->create();
    });

    it('filters by class', function () {
        $page = Livewire::test('pages::schedule')->set('classTypeId', (string) $this->yoga->id);

        expect($page->instance()->classesByDay->flatten()->pluck('id')->all())->toBe([$this->yogaWithSam->id]);
    });

    it('filters by trainer', function () {
        $page = Livewire::test('pages::schedule')->set('trainerId', (string) $this->lee->id);

        expect($page->instance()->classesByDay->flatten()->pluck('id')->all())->toBe([$this->spinWithLee->id]);
    });

    it('reads filters from the URL', function () {
        $page = Livewire::withQueryParams(['class' => (string) $this->spin->id])->test('pages::schedule');

        expect($page->instance()->classesByDay->flatten()->pluck('id')->all())->toBe([$this->spinWithLee->id]);
    });
});

describe('booking', function () {
    it('books a spot and shows it as booked', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->create();

        $page = Livewire::test('pages::schedule')->call('book', $slot->id);

        expect($this->member->bookedSlots->pluck('id')->all())->toBe([$slot->id]);
        $page->assertSee('data-state="booked"', false);
    });

    it('explains a full class, without booking it', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->capacity(1)->create();
        Booking::factory()->create(['slot_id' => $slot->id]);

        $page = Livewire::test('pages::schedule')->call('book', $slot->id);

        expect($this->member->bookings()->count())->toBe(0);
        $page->assertDispatched('toast-show', fn (string $name, array $params) => $params['slots']['text'] === 'Sorry, this class is full.');
    });

    it('sends a guest to log in rather than booking', function () {
        $slot = Slot::factory()->create();

        Livewire::test('pages::schedule')->call('book', $slot->id)->assertRedirect(route('login'));

        expect(Booking::query()->count())->toBe(0);
    });

    it('shows the latest count when it re-renders', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->capacity(3)->create();

        $page = Livewire::test('pages::schedule')->assertSee('3 spots left');

        Booking::factory()->create(['slot_id' => $slot->id]);

        $page->call('$refresh')->assertSee('2 spots left');
    });
});

describe('cancelling', function () {
    it('cancels the member\'s own booking', function () {
        $this->actingAs($this->member);
        $booking = Booking::factory()->create(['user_id' => $this->member->id]);

        Livewire::test('pages::schedule')->call('cancel', $booking->id);

        expect($booking->fresh()->isActive())->toBeFalse();
    });

    it('explains the cutoff instead of cancelling', function () {
        $this->actingAs($this->member);
        $slot = Slot::factory()->at(now()->addMinutes(30))->create();
        $booking = Booking::factory()->create(['user_id' => $this->member->id, 'slot_id' => $slot->id]);

        Livewire::test('pages::schedule')
            ->assertSee('Too late to cancel')
            ->call('cancel', $booking->id)
            ->assertDispatched('toast-show', fn (string $name, array $params) => str_contains($params['slots']['text'], 'up to 2 hours'));

        expect($booking->fresh()->isActive())->toBeTrue();
    });

    it('won\'t cancel someone else\'s booking', function () {
        $this->actingAs($this->member);
        $booking = Booking::factory()->create();

        Livewire::test('pages::schedule')->call('cancel', $booking->id);

        expect($booking->fresh()->isActive())->toBeTrue();
    });

    it('sends a guest to log in rather than cancelling', function () {
        $booking = Booking::factory()->create();

        Livewire::test('pages::schedule')->call('cancel', $booking->id)->assertRedirect(route('login'));

        expect($booking->fresh()->isActive())->toBeTrue();
    });
});

describe('card states', function () {
    it('picks the right state', function (Closure $setup, bool $booked, SlotState $expected) {
        expect(SlotState::for($setup()->fresh(), $booked))->toBe($expected);
    })->with([
        'open' => [fn () => Slot::factory()->capacity(10)->create(), false, SlotState::Open],
        'few left at the threshold' => [fn () => Slot::factory()->capacity(3)->create(), false, SlotState::FewLeft],
        'full' => [function () {
            $slot = Slot::factory()->capacity(1)->create();
            Booking::factory()->create(['slot_id' => $slot->id]);

            return $slot;
        }, false, SlotState::Full],
        'booked beats full' => [function () {
            $slot = Slot::factory()->capacity(1)->create();
            Booking::factory()->create(['slot_id' => $slot->id]);

            return $slot;
        }, true, SlotState::Booked],
        'past beats booked' => [fn () => Slot::factory()->at(now()->subHour())->create(), true, SlotState::Past],
        'cancelled beats past' => [fn () => Slot::factory()->at(now()->subHour())->cancelled()->create(), false, SlotState::Cancelled],
        'not open yet' => [fn () => Slot::factory()->at(now()->addDays(15))->create(), false, SlotState::NotOpenYet],
    ]);
});
