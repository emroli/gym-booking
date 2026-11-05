<?php

namespace Database\Factories;

use App\Enums\ClassColor;
use App\Models\ClassType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassType>
 */
class ClassTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(ClassColor::cases()),
            'default_capacity' => 10,
            'default_duration_minutes' => 60,
        ];
    }
}
