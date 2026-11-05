<?php

namespace App\Actions;

use App\Data\SlotData;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSlot
{
    /**
     * @throws ValidationException
     */
    public function handle(Slot $slot, SlotData $data): Slot
    {
        return DB::transaction(function () use ($slot, $data): Slot {
            $slot = Slot::query()->lockForUpdate()->findOrFail($slot->id);

            $this->ensureValid($slot, $data);

            $slot->fill([
                'class_type_id' => $data->classTypeId,
                'trainer_id' => $data->trainerId,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'capacity' => $data->capacity,
            ])->save();

            return $slot;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureValid(Slot $slot, SlotData $data): void
    {
        $booked = $slot->bookings()->active()->count();

        $timeChanged = ! $slot->starts_at->equalTo($data->startsAt)
            || ! $slot->ends_at->equalTo($data->endsAt);

        $errors = array_filter([
            'capacity' => $data->capacity < $booked
                ? "Capacity can't be lower than the {$booked} members already booked."
                : null,

            // Moving a booked class could silently create overlaps for members.
            'starts_at' => match (true) {
                $timeChanged && $booked > 0 => "The time can't change once members have booked. Cancel this class and create a new one.",
                $timeChanged && $data->startsAt->lessThanOrEqualTo(now()) => 'The class must start in the future.',
                default => null,
            },

            'trainer_id' => Slot::query()
                ->whereKeyNot($slot->id)
                ->whereNull('cancelled_at')
                ->where('trainer_id', $data->trainerId)
                ->overlapping($data->startsAt, $data->endsAt)
                ->exists()
                ? 'This trainer already has a class at this time.'
                : null,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
