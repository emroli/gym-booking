<?php

use App\Actions\CancelBooking;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My bookings')] class extends Component {
    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return Auth::user()
            ->bookings()
            ->with(['slot.classType', 'slot.trainer'])
            ->get()
            ->toBase();
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return $this->bookings
            ->filter(fn (Booking $booking): bool => $booking->isActive() && ! $booking->slot->hasStarted())
            ->sortBy(fn (Booking $booking) => $booking->slot->starts_at)
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->bookings
            ->reject(fn (Booking $booking): bool => $booking->isActive() && ! $booking->slot->hasStarted())
            ->sortByDesc(fn (Booking $booking) => $booking->slot->starts_at)
            ->take(30)
            ->values();
    }

    public function cancel(int $bookingId, CancelBooking $cancelBooking): void
    {
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

<div class="flex max-w-3xl flex-col gap-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('My bookings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Bookings can be cancelled up to :hours hours before the class.', ['hours' => config('booking.cancellation_cutoff_hours')]) }}</flux:text>
    </div>

    <section class="flex flex-col gap-3" aria-labelledby="upcoming-heading">
        <flux:heading id="upcoming-heading" size="lg" level="2">{{ __('Upcoming') }}</flux:heading>

        @forelse ($this->upcoming as $booking)
            <div wire:key="upcoming-{{ $booking->id }}" @class([
                'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 border-l-4 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900',
                $booking->slot->classType->color->accentClass(),
            ])>
                <div>
                    <p class="font-semibold text-zinc-800 dark:text-white">{{ $booking->slot->classType->name }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $booking->slot->starts_at->format('D j M, H:i') }}–{{ $booking->slot->ends_at->format('H:i') }} · {{ $booking->slot->trainer->name }}
                    </p>
                </div>

                @if ($booking->slot->isCancelled())
                    <flux:badge color="red" size="sm">{{ __('Class cancelled') }}</flux:badge>
                @elsecan('cancel', $booking)
                    <flux:button
                        size="sm"
                        wire:click="cancel({{ $booking->id }})"
                        wire:confirm="{{ __('Cancel your booking for this class?') }}"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                @else
                    <flux:text size="sm">{{ __('Too late to cancel') }}</flux:text>
                @endif
            </div>
        @empty
            <flux:callout icon="calendar-days" :heading="__('Nothing booked yet')">
                <flux:callout.text>
                    {{ __('Find a class on the') }}
                    <flux:link :href="route('schedule')" wire:navigate>{{ __('schedule') }}</flux:link>.
                </flux:callout.text>
            </flux:callout>
        @endforelse
    </section>

    @if ($this->history->isNotEmpty())
        <section class="flex flex-col gap-3" aria-labelledby="history-heading">
            <flux:heading id="history-heading" size="lg" level="2">{{ __('History') }}</flux:heading>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->history as $booking)
                    <li wire:key="history-{{ $booking->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-800 dark:text-white">{{ $booking->slot->classType->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->slot->starts_at->format('D j M Y, H:i') }}</p>
                        </div>

                        @if (! $booking->isActive())
                            <flux:badge size="sm" color="zinc">{{ __('You cancelled') }}</flux:badge>
                        @elseif ($booking->slot->isCancelled())
                            <flux:badge size="sm" color="red">{{ __('Class cancelled') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Finished') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
