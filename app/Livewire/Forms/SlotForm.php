<?php

namespace App\Livewire\Forms;

use App\Data\SlotData;
use App\Models\ClassType;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SlotForm extends Form
{
    private const string DATETIME_FORMAT = 'Y-m-d\TH:i';

    public string $class_type_id = '';

    public string $trainer_id = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $capacity = '';

    public string $repeat_until = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'class_type_id' => ['required', 'integer', Rule::exists('class_types', 'id')],
            'trainer_id' => ['required', 'integer', Rule::exists('trainers', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', $this->endsAfterStart(...)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'repeat_until' => ['nullable', 'date', $this->repeatsWithinLimit(...)],
        ];
    }

    private function endsAfterStart(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtotime((string) $value) <= strtotime($this->starts_at)) {
            $fail('The class must end after it starts.');
        }
    }

    private function repeatsWithinLimit(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtotime($this->starts_at) === false) {
            return;
        }

        $start = CarbonImmutable::parse($this->starts_at);
        $until = CarbonImmutable::parse((string) $value)->endOfDay();
        $weeks = config()->integer('booking.max_repeat_weeks');

        if ($until->lessThanOrEqualTo($start)) {
            $fail('The repeat date must be after the first class.');
        } elseif ($until->greaterThan($start->addWeeks($weeks)->endOfDay())) {
            $fail("A series can repeat for up to {$weeks} weeks.");
        }
    }

    public function fillFromSlot(Slot $slot): void
    {
        $this->class_type_id = (string) $slot->class_type_id;
        $this->trainer_id = (string) $slot->trainer_id;
        $this->starts_at = $slot->starts_at->format(self::DATETIME_FORMAT);
        $this->ends_at = $slot->ends_at->format(self::DATETIME_FORMAT);
        $this->capacity = (string) $slot->capacity;
        $this->repeat_until = '';
    }

    /**
     * Prefills capacity and end time from the class type's defaults.
     */
    public function applyClassTypeDefaults(): void
    {
        $classType = ClassType::query()->find($this->class_type_id);

        if ($classType === null) {
            return;
        }

        $this->capacity = (string) $classType->default_capacity;

        if (strtotime($this->starts_at) !== false) {
            $this->ends_at = CarbonImmutable::parse($this->starts_at)
                ->addMinutes($classType->default_duration_minutes)
                ->format(self::DATETIME_FORMAT);
        }
    }

    public function toData(): SlotData
    {
        return new SlotData(
            classTypeId: (int) $this->class_type_id,
            trainerId: (int) $this->trainer_id,
            startsAt: CarbonImmutable::parse($this->starts_at),
            endsAt: CarbonImmutable::parse($this->ends_at),
            capacity: (int) $this->capacity,
        );
    }

    public function repeatUntil(): ?CarbonImmutable
    {
        return $this->repeat_until === ''
            ? null
            : CarbonImmutable::parse($this->repeat_until)->endOfDay();
    }
}
