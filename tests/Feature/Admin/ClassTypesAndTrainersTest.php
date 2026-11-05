<?php

use App\Enums\ClassColor;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

describe('class types', function () {
    it('creates one', function () {
        Livewire::test('pages::admin.class-types')
            ->call('create')
            ->set('name', 'Kettlebells')
            ->set('color', ClassColor::Rose->value)
            ->set('default_capacity', 8)
            ->set('default_duration_minutes', 30)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Kettlebells');

        $classType = ClassType::query()->sole();

        expect($classType->color)->toBe(ClassColor::Rose)
            ->and($classType->default_capacity)->toBe(8);
    });

    it('updates one, and can keep its own name', function () {
        $classType = ClassType::factory()->create(['name' => 'Yoga']);

        Livewire::test('pages::admin.class-types')
            ->call('edit', $classType->id)
            ->assertSet('name', 'Yoga')
            ->set('default_capacity', 25)
            ->call('save')
            ->assertHasNoErrors();

        expect($classType->fresh()->default_capacity)->toBe(25);
    });

    it('rejects a duplicate name', function () {
        ClassType::factory()->create(['name' => 'Yoga']);

        Livewire::test('pages::admin.class-types')
            ->call('create')
            ->set('name', 'Yoga')
            ->call('save')
            ->assertHasErrors('name');
    });

    it('rejects a color outside the enum', function () {
        Livewire::test('pages::admin.class-types')
            ->call('create')
            ->set('name', 'Yoga')
            ->set('color', 'plaid')
            ->call('save')
            ->assertHasErrors('color');
    });

    it('deletes one without slots', function () {
        $classType = ClassType::factory()->create();

        Livewire::test('pages::admin.class-types')->call('delete', $classType->id);

        expect(ClassType::query()->find($classType->id))->toBeNull();
    });

    it('keeps one that has slots', function () {
        $classType = Slot::factory()->create()->classType;

        Livewire::test('pages::admin.class-types')->call('delete', $classType->id);

        expect(ClassType::query()->find($classType->id))->not->toBeNull();
    });
});

describe('trainers', function () {
    it('creates one', function () {
        Livewire::test('pages::admin.trainers')
            ->call('create')
            ->set('name', 'Robin Vale')
            ->set('bio', 'Former sprinter.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Robin Vale');

        expect(Trainer::query()->sole()->bio)->toBe('Former sprinter.');
    });

    it('updates one', function () {
        $trainer = Trainer::factory()->create();

        Livewire::test('pages::admin.trainers')
            ->call('edit', $trainer->id)
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        expect($trainer->fresh()->name)->toBe('New Name');
    });

    it('requires a name', function () {
        Livewire::test('pages::admin.trainers')
            ->call('create')
            ->call('save')
            ->assertHasErrors('name');
    });

    it('deletes one without slots', function () {
        $trainer = Trainer::factory()->create();

        Livewire::test('pages::admin.trainers')->call('delete', $trainer->id);

        expect(Trainer::query()->find($trainer->id))->toBeNull();
    });

    it('keeps one who has slots', function () {
        $trainer = Slot::factory()->create()->trainer;

        Livewire::test('pages::admin.trainers')->call('delete', $trainer->id);

        expect(Trainer::query()->find($trainer->id))->not->toBeNull();
    });
});
