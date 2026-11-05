<?php

use App\Actions\CancelSlot;
use App\Actions\ScheduleSlots;
use App\Actions\UpdateSlot;
use App\Enums\SlotState;
use App\Livewire\Forms\SlotForm;
use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage slots')] class extends Component {
    use WithPagination;

    public SlotForm $form;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    #[Url]
    public bool $includePast = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Slot::class);
    }

    public function updatedIncludePast(): void
    {
        $this->resetPage();
    }

    public function updatedFormClassTypeId(): void
    {
        $this->form->applyClassTypeDefaults();
    }

    /**
     * @return LengthAwarePaginator<int, Slot>
     */
    #[Computed]
    public function classSlots(): LengthAwarePaginator
    {
        return Slot::query()
            ->with(['classType', 'trainer'])
            ->withAvailability()
            ->when(! $this->includePast, fn ($query) => $query->where('starts_at', '>', now()))
            ->orderBy('starts_at', $this->includePast ? 'desc' : 'asc')
            ->paginate(15);
    }

    /**
     * @return Collection<int, ClassType>
     */
    #[Computed]
    public function classTypes(): Collection
    {
        return ClassType::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Trainer>
     */
    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::query()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Slot::class);

        $this->form->reset();
        $this->resetValidation();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $slotId): void
    {
        $slot = Slot::query()->findOrFail($slotId);

        if (! $this->allowed('update', $slot)) {
            return;
        }

        $this->form->fillFromSlot($slot);
        $this->resetValidation();
        $this->editingId = $slot->id;
        $this->showForm = true;
    }

    public function save(ScheduleSlots $schedule, UpdateSlot $update): void
    {
        $slot = $this->editingId === null ? null : Slot::query()->findOrFail($this->editingId);

        if ($slot === null) {
            $this->authorize('create', Slot::class);
        } elseif (! $this->allowed('update', $slot)) {
            return;
        }

        $this->form->validate();

        try {
            if ($slot === null) {
                $count = $schedule->handle($this->form->toData(), $this->form->repeatUntil())->count();
                $message = $count === 1 ? __('Class added.') : __(':count classes added.', ['count' => $count]);
            } else {
                $update->handle($slot, $this->form->toData());
                $message = __('Class updated.');
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError("form.{$field}", $messages[0]);
            }

            return;
        }

        $this->showForm = false;
        $this->form->reset();

        Flux::toast(variant: 'success', text: $message);
    }

    public function cancel(int $slotId, CancelSlot $cancelSlot): void
    {
        $slot = Slot::query()->findOrFail($slotId);

        if (! $this->allowed('cancel', $slot)) {
            return;
        }

        $cancelSlot->handle($slot);

        Flux::toast(variant: 'success', text: __('Class cancelled.'));
    }

    /**
     * Shows the policy's reason, because a class can start while this page is open.
     */
    private function allowed(string $ability, Slot $slot): bool
    {
        $this->authorize('viewAny', Slot::class);

        $response = Gate::inspect($ability, $slot);

        if ($response->denied()) {
            Flux::toast(variant: 'danger', text: (string) $response->message());
        }

        return $response->allowed();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Manage slots') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add, change and cancel classes on the schedule.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add class') }}</flux:button>
    </div>

    <flux:switch wire:model.live="includePast" :label="__('Include finished classes')" align="left" class="self-start" />

    @if ($this->classSlots->isEmpty())
        <flux:callout icon="calendar-days" :heading="__('No classes yet')" :text="__('Add a class to start building the schedule.')" />
    @else
        <flux:table :paginate="$this->classSlots">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Trainer') }}</flux:table.column>
                <flux:table.column>{{ __('Booked') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->classSlots as $classSlot)
                    @php($state = SlotState::for($classSlot))

                    <flux:table.row :key="$classSlot->id">
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $classSlot->starts_at->format('D j M, H:i') }}–{{ $classSlot->ends_at->format('H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="inline-flex items-center gap-2">
                                <span @class(['size-2 rounded-full', $classSlot->classType->color->dotClass()])></span>
                                {{ $classSlot->classType->name }}
                                @if ($classSlot->series_id)
                                    <flux:tooltip :content="__('Part of a weekly series')">
                                        <flux:icon.arrow-path variant="micro" class="text-zinc-400" />
                                    </flux:tooltip>
                                @endif
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $classSlot->trainer->name }}</flux:table.cell>
                        <flux:table.cell>{{ $classSlot->active_bookings_count }} / {{ $classSlot->capacity }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$state->color()">{{ $state->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="users" :href="route('admin.slots.attendees', $classSlot)" wire:navigate :aria-label="__('Attendees')" />

                            @can('update', $classSlot)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $classSlot->id }})" :aria-label="__('Edit')" />
                                <flux:button size="sm" variant="ghost" icon="x-circle" wire:click="cancel({{ $classSlot->id }})" wire:confirm="{{ __('Cancel this class? Members will see it as cancelled.') }}" :aria-label="__('Cancel class')" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit class') : __('Add class') }}</flux:heading>

            <flux:select wire:model.live="form.class_type_id" :label="__('Class type')" :placeholder="__('Choose a class type')">
                @foreach ($this->classTypes as $classType)
                    <flux:select.option value="{{ $classType->id }}">{{ $classType->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.trainer_id" :label="__('Trainer')" :placeholder="__('Choose a trainer')">
                @foreach ($this->trainers as $trainer)
                    <flux:select.option value="{{ $trainer->id }}">{{ $trainer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input type="datetime-local" wire:model.live.blur="form.starts_at" :label="__('Starts')" />
                <flux:input type="datetime-local" wire:model="form.ends_at" :label="__('Ends')" />
            </div>

            <flux:input type="number" min="1" max="500" wire:model="form.capacity" :label="__('Capacity')" />

            @if ($editingId === null)
                <flux:input
                    type="date"
                    wire:model="form.repeat_until"
                    :label="__('Repeat weekly until')"
                    :description="__('Optional. Each class can still be edited or cancelled on its own.')"
                />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
