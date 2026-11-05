<?php

use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    $this->actingAs(User::factory()->admin()->create());
    $this->classType = ClassType::factory()->create(['default_capacity' => 12, 'default_duration_minutes' => 45]);
    $this->trainer = Trainer::factory()->create();
});

function slotForm(array $overrides = []): Testable
{
    $page = Livewire::test('pages::admin.slots')->call('create');

    $values = [
        'class_type_id' => test()->classType->id,
        'trainer_id' => test()->trainer->id,
        'starts_at' => '2026-11-10T18:00',
        'ends_at' => '2026-11-10T19:00',
        'capacity' => 10,
        ...$overrides,
    ];

    foreach ($values as $field => $value) {
        $page->set("form.{$field}", $value);
    }

    return $page;
}

describe('adding a class', function () {
    it('creates a single slot', function () {
        slotForm()->call('save')->assertHasNoErrors()->assertSet('showForm', false);

        $slot = Slot::query()->sole();

        expect($slot->starts_at->format('Y-m-d H:i'))->toBe('2026-11-10 18:00')
            ->and($slot->ends_at->format('Y-m-d H:i'))->toBe('2026-11-10 19:00')
            ->and($slot->capacity)->toBe(10)
            ->and($slot->series_id)->toBeNull();
    });

    it('prefills capacity and end time from the class type', function () {
        Livewire::test('pages::admin.slots')
            ->call('create')
            ->set('form.starts_at', '2026-11-10T18:00')
            ->set('form.class_type_id', $this->classType->id)
            ->assertSet('form.capacity', '12')
            ->assertSet('form.ends_at', '2026-11-10T18:45');
    });

    it('requires an end after the start', function (string $endsAt) {
        slotForm(['ends_at' => $endsAt])->call('save')->assertHasErrors('form.ends_at');

        expect(Slot::query()->count())->toBe(0);
    })->with(['same time' => '2026-11-10T18:00', 'before' => '2026-11-10T17:00']);

    it('requires a start in the future', function () {
        slotForm(['starts_at' => '2026-11-01T18:00', 'ends_at' => '2026-11-01T19:00'])
            ->call('save')
            ->assertHasErrors('form.starts_at');

        expect(Slot::query()->count())->toBe(0);
    });

    it('validates its fields', function (string $field, mixed $value) {
        slotForm([$field => $value])->call('save')->assertHasErrors("form.{$field}");
    })->with([
        'no class type' => ['class_type_id', ''],
        'unknown class type' => ['class_type_id', 99999],
        'no trainer' => ['trainer_id', ''],
        'unknown trainer' => ['trainer_id', 99999],
        'zero capacity' => ['capacity', 0],
        'negative capacity' => ['capacity', -1],
        'text capacity' => ['capacity', 'lots'],
        'bad start' => ['starts_at', 'nonsense'],
    ]);

    it('uses plain field names in its messages', function () {
        $page = Livewire::test('pages::admin.slots')->call('create')->call('save');

        expect($page->errors()->first('form.class_type_id'))->toBe('The class type field is required.')
            ->and($page->errors()->first('form.trainer_id'))->toBe('The trainer field is required.')
            ->and($page->errors()->first('form.starts_at'))->toBe('The start time field is required.');
    });

    it('rejects a trainer who is already teaching', function () {
        Slot::factory()->for($this->trainer)->at(CarbonImmutable::parse('2026-11-10 18:30'))->create();

        slotForm()->call('save')->assertHasErrors('form.trainer_id');

        expect(Slot::query()->count())->toBe(1);
    });

    it('allows the trainer\'s time back if their other class was cancelled', function () {
        Slot::factory()->for($this->trainer)->at(CarbonImmutable::parse('2026-11-10 18:30'))->cancelled()->create();

        slotForm()->call('save')->assertHasNoErrors();

        expect(Slot::query()->count())->toBe(2);
    });
});

describe('repeating a class', function () {
    it('creates one slot per week, including the last date', function () {
        slotForm(['repeat_until' => '2026-12-01'])->call('save')->assertHasNoErrors();

        $slots = Slot::query()->orderBy('starts_at')->get();

        expect($slots->map(fn (Slot $slot) => $slot->starts_at->format('Y-m-d H:i'))->all())->toBe([
            '2026-11-10 18:00',
            '2026-11-17 18:00',
            '2026-11-24 18:00',
            '2026-12-01 18:00',
        ])
            ->and($slots->pluck('series_id')->unique())->toHaveCount(1)
            ->and($slots->first()->series_id)->not->toBeNull();
    });

    it('keeps the class length on every date', function () {
        slotForm(['repeat_until' => '2026-11-17'])->call('save');

        Slot::query()->each(fn (Slot $slot) => expect($slot->starts_at->diffInMinutes($slot->ends_at))->toEqual(60));
    });

    it('creates real slots that can be changed one at a time', function () {
        slotForm(['repeat_until' => '2026-11-17'])->call('save');
        [$first, $second] = Slot::query()->orderBy('starts_at')->get()->all();

        Livewire::test('pages::admin.slots')
            ->call('edit', $first->id)
            ->set('form.capacity', 3)
            ->call('save')
            ->assertHasNoErrors();

        expect($first->fresh()->capacity)->toBe(3)
            ->and($second->fresh()->capacity)->toBe(10);
    });

    it('creates nothing if any date clashes, and lists the clashes', function () {
        Slot::factory()->for($this->trainer)->at(CarbonImmutable::parse('2026-11-24 18:00'))->create();

        $page = slotForm(['repeat_until' => '2026-12-01'])->call('save')->assertHasErrors('form.trainer_id');

        expect($page->errors()->first('form.trainer_id'))->toContain('24 Nov 2026')
            ->and(Slot::query()->count())->toBe(1);
    });

    it('allows exactly the maximum number of weeks', function () {
        slotForm(['repeat_until' => CarbonImmutable::parse('2026-11-10')->addWeeks(26)->format('Y-m-d')])
            ->call('save')
            ->assertHasNoErrors();

        expect(Slot::query()->count())->toBe(27);
    });

    it('rejects going over the maximum number of weeks', function () {
        slotForm(['repeat_until' => CarbonImmutable::parse('2026-11-10')->addWeeks(26)->addDay()->format('Y-m-d')])
            ->call('save')
            ->assertHasErrors('form.repeat_until');

        expect(Slot::query()->count())->toBe(0);
    });

    it('rejects a repeat date before the first class', function () {
        slotForm(['repeat_until' => '2026-11-09'])->call('save')->assertHasErrors('form.repeat_until');

        expect(Slot::query()->count())->toBe(0);
    });
});

describe('editing a class', function () {
    beforeEach(function () {
        $this->slot = Slot::factory()
            ->for($this->classType)
            ->for($this->trainer)
            ->at(CarbonImmutable::parse('2026-11-10 18:00'))
            ->capacity(10)
            ->create();
    });

    it('fills the form and saves changes', function () {
        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->assertSet('form.starts_at', '2026-11-10T18:00')
            ->assertSet('form.capacity', '10')
            ->set('form.capacity', 20)
            ->call('save')
            ->assertHasNoErrors();

        expect($this->slot->fresh()->capacity)->toBe(20);
    });

    it('moves a class that has no bookings', function () {
        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.starts_at', '2026-11-11T18:00')
            ->set('form.ends_at', '2026-11-11T19:00')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->slot->fresh()->starts_at->format('Y-m-d'))->toBe('2026-11-11');
    });

    it('won\'t move a class once members have booked', function () {
        Booking::factory()->create(['slot_id' => $this->slot->id]);

        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.starts_at', '2026-11-11T18:00')
            ->set('form.ends_at', '2026-11-11T19:00')
            ->call('save')
            ->assertHasErrors('form.starts_at');

        expect($this->slot->fresh()->starts_at->format('Y-m-d'))->toBe('2026-11-10');
    });

    it('moves a class whose bookings were all cancelled', function () {
        Booking::factory()->cancelled()->create(['slot_id' => $this->slot->id]);

        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.starts_at', '2026-11-11T18:00')
            ->set('form.ends_at', '2026-11-11T19:00')
            ->call('save')
            ->assertHasNoErrors();
    });

    it('won\'t lower capacity below the booking count', function () {
        Booking::factory(3)->create(['slot_id' => $this->slot->id]);

        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.capacity', 2)
            ->call('save')
            ->assertHasErrors('form.capacity');

        expect($this->slot->fresh()->capacity)->toBe(10);
    });

    it('allows lowering capacity to exactly the booking count', function () {
        Booking::factory(3)->create(['slot_id' => $this->slot->id]);

        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.capacity', 3)
            ->call('save')
            ->assertHasNoErrors();

        expect($this->slot->fresh()->capacity)->toBe(3);
    });

    it('doesn\'t count the slot as clashing with itself', function () {
        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->call('save')
            ->assertHasNoErrors();
    });

    it('rejects switching to a trainer who is busy', function () {
        $busy = Trainer::factory()->create();
        Slot::factory()->for($busy)->at(CarbonImmutable::parse('2026-11-10 18:30'))->create();

        Livewire::test('pages::admin.slots')
            ->call('edit', $this->slot->id)
            ->set('form.trainer_id', $busy->id)
            ->call('save')
            ->assertHasErrors('form.trainer_id');
    });

    it('explains why a finished class can\'t be edited', function () {
        $page = Livewire::test('pages::admin.slots');

        $this->travelTo(CarbonImmutable::parse('2026-11-10 18:01'));

        $page->call('edit', $this->slot->id)->assertSet('showForm', false);
    });

    it('won\'t save a class that started while the form was open', function () {
        $page = Livewire::test('pages::admin.slots')->call('edit', $this->slot->id)->set('form.capacity', 99);

        $this->travelTo(CarbonImmutable::parse('2026-11-10 18:01'));

        $page->call('save');

        expect($this->slot->fresh()->capacity)->toBe(10);
    });
});

describe('cancelling a class', function () {
    it('cancels it and keeps its bookings', function () {
        $booking = Booking::factory()->create();

        Livewire::test('pages::admin.slots')->call('cancel', $booking->slot_id);

        expect($booking->slot->fresh()->isCancelled())->toBeTrue()
            ->and($booking->fresh())->not->toBeNull();
    });

    it('won\'t cancel a class that has started', function () {
        $slot = Slot::factory()->at(now()->subMinute())->create();

        Livewire::test('pages::admin.slots', ['includePast' => true])->call('cancel', $slot->id);

        expect($slot->fresh()->isCancelled())->toBeFalse();
    });
});

describe('the list', function () {
    it('shows upcoming classes and hides finished ones by default', function () {
        $upcoming = Slot::factory()->create();
        $finished = Slot::factory()->at(now()->subDay())->create();

        $page = Livewire::test('pages::admin.slots');

        expect($page->instance()->classSlots->pluck('id')->all())->toBe([$upcoming->id]);

        $page->set('includePast', true);

        expect($page->instance()->classSlots->pluck('id')->all())->toBe([$upcoming->id, $finished->id]);
    });

    it('shows booked count against capacity', function () {
        $slot = Slot::factory()->capacity(7)->create();
        Booking::factory(2)->create(['slot_id' => $slot->id]);
        Booking::factory()->cancelled()->create(['slot_id' => $slot->id]);

        Livewire::test('pages::admin.slots')->assertSee('2 / 7');
    });
});

describe('attendees', function () {
    it('lists active members only', function () {
        $slot = Slot::factory()->create();
        $going = User::factory()->create(['name' => 'Going Member']);
        $gone = User::factory()->create(['name' => 'Gone Member']);
        Booking::factory()->create(['slot_id' => $slot->id, 'user_id' => $going->id]);
        Booking::factory()->cancelled()->create(['slot_id' => $slot->id, 'user_id' => $gone->id]);

        Livewire::test('pages::admin.slot-attendees', ['slot' => $slot])
            ->assertSee('Going Member')
            ->assertDontSee('Gone Member');
    });

    it('lets an admin cancel a member\'s booking after the cutoff', function () {
        $slot = Slot::factory()->at(now()->addMinutes(10))->create();
        $booking = Booking::factory()->create(['slot_id' => $slot->id]);

        Livewire::test('pages::admin.slot-attendees', ['slot' => $slot])
            ->call('cancelBooking', $booking->id);

        expect($booking->fresh()->isActive())->toBeFalse();
    });

    it('won\'t cancel a booking from a different slot', function () {
        $slot = Slot::factory()->create();
        $other = Booking::factory()->create();

        Livewire::test('pages::admin.slot-attendees', ['slot' => $slot])
            ->call('cancelBooking', $other->id)
            ->assertNotFound();

        expect($other->fresh()->isActive())->toBeTrue();
    });
});
