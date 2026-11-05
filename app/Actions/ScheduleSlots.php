<?php

namespace App\Actions;

use App\Data\SlotData;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ScheduleSlots
{
    /**
     * Creates one slot, or a weekly series when $repeatUntil is given. A series
     * is all-or-nothing: one clash rejects it and lists every clashing date.
     *
     * @return Collection<int, Slot>
     *
     * @throws ValidationException
     */
    public function handle(SlotData $data, ?CarbonImmutable $repeatUntil = null): Collection
    {
        if ($data->startsAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['starts_at' => 'The class must start in the future.']);
        }

        return DB::transaction(function () use ($data, $repeatUntil): Collection {
            $starts = $this->occurrences($data->startsAt, $repeatUntil);
            $minutes = $data->minutes();

            $clashes = $starts->filter(fn (CarbonImmutable $start): bool => Slot::query()
                ->whereNull('cancelled_at')
                ->where('trainer_id', $data->trainerId)
                ->overlapping($start, $start->addMinutes($minutes))
                ->exists());

            if ($clashes->isNotEmpty()) {
                $dates = $clashes->map(fn (CarbonImmutable $start): string => $start->format('j M Y'))->implode(', ');

                throw ValidationException::withMessages(['trainer_id' => "This trainer already has a class on: {$dates}."]);
            }

            $seriesId = $starts->count() > 1 ? (string) Str::uuid() : null;

            return $starts->map(fn (CarbonImmutable $start): Slot => Slot::query()->create([
                'class_type_id' => $data->classTypeId,
                'trainer_id' => $data->trainerId,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($minutes),
                'capacity' => $data->capacity,
                'series_id' => $seriesId,
            ]));
        });
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function occurrences(CarbonImmutable $start, ?CarbonImmutable $repeatUntil): Collection
    {
        $starts = collect([$start]);

        if ($repeatUntil === null) {
            return $starts;
        }

        for ($next = $start->addWeek(); $next->lessThanOrEqualTo($repeatUntil); $next = $next->addWeek()) {
            $starts->push($next);
        }

        return $starts;
    }
}
