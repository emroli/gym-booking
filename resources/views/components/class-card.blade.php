@props([
    'classSlot',
    'booking' => null,
])

@php
    $state = App\Enums\SlotState::for($classSlot, $booking !== null);
@endphp

<article
    wire:key="class-{{ $classSlot->id }}"
    data-slot-id="{{ $classSlot->id }}"
    data-state="{{ $state->value }}"
    @class([
        'flex flex-col gap-2 rounded-lg border border-zinc-200 border-l-4 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900',
        $classSlot->classType->color->accentClass(),
        'opacity-60' => in_array($state, [App\Enums\SlotState::Past, App\Enums\SlotState::Cancelled]),
    ])
>
    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
        <time datetime="{{ $classSlot->starts_at->toIso8601String() }}" class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
            {{ $classSlot->starts_at->format('H:i') }}–{{ $classSlot->ends_at->format('H:i') }}
        </time>

        <flux:badge size="sm" :color="$state->color()">{{ __($state->label()) }}</flux:badge>
    </div>

    <div>
        <h3 @class([
            'text-sm font-semibold text-zinc-800 dark:text-white',
            'line-through' => $state === App\Enums\SlotState::Cancelled,
        ])>{{ $classSlot->classType->name }}</h3>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $classSlot->trainer->name }}</p>
    </div>

    @unless (in_array($state, [App\Enums\SlotState::Past, App\Enums\SlotState::Cancelled, App\Enums\SlotState::Full]))
        <p class="text-xs text-zinc-600 dark:text-zinc-300" data-spots-left>
            {{ trans_choice(':count spot left|:count spots left', $classSlot->spots_left) }}
        </p>
    @endunless

    @if ($state === App\Enums\SlotState::Booked)
        @can('cancel', $booking)
            <flux:button
                size="xs"
                wire:click="cancel({{ $booking->id }})"
                wire:confirm="{{ __('Cancel your booking for this class?') }}"
                class="w-full"
            >
                {{ __('Cancel') }}
            </flux:button>
        @else
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Too late to cancel') }}</p>
        @endcan
    @elseif ($state->canBeBooked())
        @auth
            <flux:button
                size="xs"
                variant="primary"
                wire:click="book({{ $classSlot->id }})"
                wire:loading.attr="disabled"
                class="w-full"
            >
                {{ __('Book') }}
            </flux:button>
        @else
            <flux:button size="xs" :href="route('login')" wire:navigate class="w-full">
                {{ __('Log in to book') }}
            </flux:button>
        @endauth
    @endif
</article>
