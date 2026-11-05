<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\SlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $class_type_id
 * @property int $trainer_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $capacity
 * @property string|null $series_id
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int|null $active_bookings_count
 * @property-read int $spots_left
 */
#[Fillable(['class_type_id', 'trainer_id', 'starts_at', 'ends_at', 'capacity', 'series_id'])]
class Slot extends Model
{
    /** @use HasFactory<SlotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ClassType, $this>
     */
    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    /**
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * Every booking for this slot, including cancelled ones.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Members currently holding a spot.
     *
     * @return BelongsToMany<User, $this, Booking>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookings')
            ->using(Booking::class)
            ->withPivot('id', 'cancelled_at')
            ->withTimestamps()
            ->wherePivotNull('cancelled_at');
    }

    /**
     * Not cancelled, and hasn't started yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereNull('cancelled_at')->where('starts_at', '>', now());
    }

    /**
     * Touching end-to-end doesn't count, so 10-11 and 11-12 don't overlap.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /**
     * Adds active_bookings_count, so spots_left needs no extra query per slot.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withAvailability(Builder $query): void
    {
        $query->withCount(['bookings as active_bookings_count' => function (Builder $bookings): void {
            $bookings->whereNull('cancelled_at');
        }]);
    }

    /**
     * @return Attribute<int<0, max>, never>
     */
    protected function spotsLeft(): Attribute
    {
        return Attribute::get(function (): int {
            $booked = $this->active_bookings_count ?? $this->bookings()->active()->count();

            return max(0, $this->capacity - $booked);
        });
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->lessThanOrEqualTo(now());
    }

    public function isWithinBookingWindow(): bool
    {
        return $this->starts_at->lessThanOrEqualTo(now()->addDays(config()->integer('booking.window_days')));
    }
}
