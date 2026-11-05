<?php

use App\Actions\BookSlot;
use App\Actions\CancelBooking;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Schedule')] class extends Component {
    /**
     * These are strings because they come from the URL, so a hand-edited link
     * should fall back to the defaults rather than cause an error.
     */
    #[Url]
    public string $week = '';

    #[Url(as: 'class')]
    public string $classTypeId = '';

    #[Url(as: 'trainer')]
    public string $trainerId = '';

    #[Computed]
    public function weekStart(): CarbonImmutable
    {
        $date = CarbonImmutable::hasFormat($this->week, 'Y-m-d')
            ? CarbonImmutable::parse($this->week)
            : CarbonImmutable::today();

        return $date->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    #[Computed]
    public function days(): Collection
    {
        return collect(range(0, 6))->map(fn (int $offset): CarbonImmutable => $this->weekStart->addDays($offset));
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Slot>>
     */
    #[Computed]
    public function classesByDay(): Collection
    {
        return Slot::query()
            ->with(['classType', 'trainer'])
            ->withAvailability()
            ->whereBetween('starts_at', [$this->weekStart, $this->weekStart->endOfWeek(CarbonImmutable::SUNDAY)])
            ->when($this->classTypeId !== '', fn ($query) => $query->where('class_type_id', $this->classTypeId))
            ->when($this->trainerId !== '', fn ($query) => $query->where('trainer_id', $this->trainerId))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Slot $classSlot): string => $classSlot->starts_at->format('Y-m-d'))
            ->toBase();
    }

    /**
     * The viewer's active bookings this week, keyed by slot ID.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function myBookings(): Collection
    {
        $user = Auth::user();

        if ($user === null) {
            return collect();
        }

        return $user->bookings()
            ->active()
            ->with('slot')
            ->whereIn('slot_id', $this->classesByDay->flatten()->pluck('id'))
            ->get()
            ->keyBy('slot_id')
            ->toBase();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ClassType>
     */
    #[Computed]
    public function classTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return ClassType::query()->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Trainer>
     */
    #[Computed]
    public function trainers(): \Illuminate\Database\Eloquent\Collection
    {
        return Trainer::query()->orderBy('name')->get();
    }

    public function previousWeek(): void
    {
        $this->week = $this->weekStart->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->week = $this->weekStart->addWeek()->format('Y-m-d');
    }

    public function thisWeek(): void
    {
        $this->week = '';
    }

    public function book(int $slotId, BookSlot $bookSlot): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $classSlot = Slot::query()->with('classType')->findOrFail($slotId);

        try {
            $bookSlot->handle($user, $classSlot);
        } catch (BookingException $exception) {
            Flux::toast(variant: 'danger', text: __($exception->getMessage()));

            return;
        }

        Flux::toast(variant: 'success', text: __('You\'re booked for :class.', ['class' => $classSlot->classType->name]));
    }

    public function cancel(int $bookingId, CancelBooking $cancelBooking): void
    {
        if (Auth::guest()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $booking = Booking::query()->findOrFail($bookingId);
        $response = Gate::inspect('cancel', $booking);

        if ($response->denied()) {
            Flux::toast(variant: 'danger', text: (string) $response->message());

            return;
        }

        $cancelBooking->handle($booking);

        Flux::toast(variant: 'success', text: __('Your booking was cancelled.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Schedule') }}</flux:heading>
            <flux:text class="mt-1" data-week-label>
                {{ $this->weekStart->format('j M') }} – {{ $this->weekStart->addDays(6)->format('j M Y') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-1">
            <flux:button size="sm" icon="chevron-left" wire:click="previousWeek" :aria-label="__('Previous week')" />
            <flux:button size="sm" wire:click="thisWeek">{{ __('This week') }}</flux:button>
            <flux:button size="sm" icon="chevron-right" wire:click="nextWeek" :aria-label="__('Next week')" />
        </div>
    </div>

    <div class="grid gap-3 sm:max-w-xl sm:grid-cols-2">
        <flux:select wire:model.live="classTypeId" :label="__('Class')">
            <flux:select.option value="">{{ __('All classes') }}</flux:select.option>
            @foreach ($this->classTypes as $classType)
                <flux:select.option value="{{ $classType->id }}">{{ $classType->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="trainerId" :label="__('Trainer')">
            <flux:select.option value="">{{ __('All trainers') }}</flux:select.option>
            @foreach ($this->trainers as $trainer)
                <flux:select.option value="{{ $trainer->id }}">{{ $trainer->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 xl:grid-cols-7 xl:gap-2">
        @foreach ($this->days as $day)
            <section wire:key="day-{{ $day->format('Y-m-d') }}" class="flex flex-col gap-2">
                <h2 @class([
                    'flex items-baseline gap-2 border-b pb-1 text-sm font-medium xl:flex-col xl:gap-0',
                    'border-zinc-800 text-zinc-900 dark:border-white dark:text-white' => $day->isToday(),
                    'border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300' => ! $day->isToday(),
                ])>
                    <span>{{ $day->format('l') }}</span>
                    <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $day->format('j M') }}</span>
                </h2>

                @forelse ($this->classesByDay->get($day->format('Y-m-d'), []) as $classSlot)
                    <x-class-card :class-slot="$classSlot" :booking="$this->myBookings->get($classSlot->id)" />
                @empty
                    <p class="py-2 text-xs text-zinc-400 dark:text-zinc-500">{{ __('No classes') }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
