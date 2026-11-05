<?php

namespace Database\Factories;

use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slot>
 */
class SlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(2)->startOfHour();

        return [
            'class_type_id' => ClassType::factory(),
            'trainer_id' => Trainer::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'capacity' => 10,
        ];
    }

    public function at(CarbonInterface $start, int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);
    }

    public function capacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
        ]);
    }
}
