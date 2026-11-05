<?php

namespace Database\Seeders;

use App\Enums\ClassColor;
use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo data for local use. The logins have a well-known password, so this
     * refuses to run in production.
     */
    public function run(): void
    {
        throw_if(app()->isProduction(), RuntimeException::class, 'The demo seeder must not run in production.');

        User::factory()->admin()->create(['name' => 'Alex Admin', 'email' => 'admin@example.com']);
        $member = User::factory()->create(['name' => 'Morgan Member', 'email' => 'member@example.com']);
        $others = User::factory(14)->create();

        $trainers = collect(['Sam Rivera', 'Jordan Lee', 'Casey Morgan'])
            ->map(fn (string $name): Trainer => Trainer::factory()->create(['name' => $name]));

        $classes = collect([
            ['HIIT', 'Short, intense intervals for the whole body.', ClassColor::Rose, 12, 45],
            ['Yoga', 'Strength, balance and breathing at a steady pace.', ClassColor::Emerald, 14, 60],
            ['Spin', 'A high-energy indoor cycling session.', ClassColor::Sky, 10, 45],
            ['Boxing', 'Pad work and footwork. Beginners are welcome.', ClassColor::Amber, 8, 60],
            ['Pilates', 'Controlled movement focused on your core.', ClassColor::Violet, 10, 50],
        ])->mapWithKeys(fn (array $class): array => [$class[0] => ClassType::factory()->create([
            'name' => $class[0],
            'description' => $class[1],
            'color' => $class[2],
            'default_capacity' => $class[3],
            'default_duration_minutes' => $class[4],
        ])]);

        $weekday = [['07:00', 'HIIT', 0], ['12:15', 'Yoga', 1], ['18:00', 'Spin', 2], ['19:30', 'Boxing', 0]];
        $weekend = [['09:30', 'Yoga', 1], ['11:00', 'Pilates', 2]];

        $count = 0;

        foreach (range(-7, 13) as $offset) {
            $day = today()->addDays($offset);

            foreach ($day->isWeekend() ? $weekend : $weekday as [$time, $className, $trainerIndex]) {
                $class = $classes[$className];
                $start = $day->setTimeFromTimeString($time);

                $slot = Slot::create([
                    'class_type_id' => $class->id,
                    'trainer_id' => $trainers[$trainerIndex]->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($class->default_duration_minutes),
                    'capacity' => $class->default_capacity,
                ]);

                $this->fill($slot, $others, $count++);
            }
        }

        $this->bookDemoMember($member);
    }

    /**
     * Gives the calendar a realistic mix of quiet, nearly full and full classes.
     *
     * @param  Collection<int, User>  $members
     */
    private function fill(Slot $slot, Collection $members, int $index): void
    {
        $booked = match ($index % 5) {
            0 => $slot->capacity,
            1 => $slot->capacity - 2,
            2 => (int) floor($slot->capacity / 2),
            default => 2,
        };

        $members->shuffle()->take($booked)->each(fn (User $user) => Booking::factory()->create([
            'user_id' => $user->id,
            'slot_id' => $slot->id,
        ]));
    }

    private function bookDemoMember(User $member): void
    {
        Slot::query()
            ->upcoming()
            ->withAvailability()
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Slot $slot): bool => $slot->spots_left > 0)
            ->skip(2)
            ->take(2)
            ->each(fn (Slot $slot) => Booking::factory()->create([
                'user_id' => $member->id,
                'slot_id' => $slot->id,
            ]));
    }
}
