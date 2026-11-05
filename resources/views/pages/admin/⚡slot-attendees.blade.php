<?php

use App\Actions\CancelBooking;
use App\Models\Booking;
use App\Models\Slot;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Attendees')] class extends Component {
    #[Locked]
    public Slot $classSlot;

    public function mount(Slot $slot): void
    {
        $this->authorize('viewAttendees', $slot);

        $this->classSlot = $slot->load(['classType', 'trainer']);
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->classSlot->bookings()
            ->active()
            ->with('user')
            ->oldest('updated_at')
            ->get();
    }

    public function cancelBooking(int $bookingId, CancelBooking $cancelBooking): void
    {
        $this->authorize('viewAttendees', $this->classSlot);

        $booking = $this->classSlot->bookings()->findOrFail($bookingId);
        $response = Gate::inspect('cancel', $booking);

        if ($response->denied()) {
            Flux::toast(variant: 'danger', text: (string) $response->message());

            return;
        }

        $cancelBooking->handle($booking);

        Flux::toast(variant: 'success', text: __('Booking cancelled.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('admin.slots')" wire:navigate class="-ms-2 mb-2">
            {{ __('All slots') }}
        </flux:button>

        <flux:heading size="xl" level="1">{{ $classSlot->classType->name }}</flux:heading>
        <flux:text class="mt-1">
            {{ $classSlot->starts_at->format('l j F, H:i') }}–{{ $classSlot->ends_at->format('H:i') }} · {{ $classSlot->trainer->name }}
        </flux:text>
    </div>

    <flux:text>{{ __(':booked of :capacity spots booked', ['booked' => $this->bookings->count(), 'capacity' => $classSlot->capacity]) }}</flux:text>

    @if ($this->bookings->isEmpty())
        <flux:callout icon="users" :heading="__('No bookings yet')" :text="__('Members who book this class will appear here.')" />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Member') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->bookings as $booking)
                    <flux:table.row :key="$booking->id">
                        <flux:table.cell>{{ $booking->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $booking->user->email }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="cancelBooking({{ $booking->id }})"
                                wire:confirm="{{ __('Cancel this member\'s booking?') }}"
                            >
                                {{ __('Cancel booking') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
