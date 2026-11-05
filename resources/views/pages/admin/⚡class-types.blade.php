<?php

use App\Enums\ClassColor;
use App\Models\ClassType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Class types')] class extends Component {
    public string $name = '';

    public string $description = '';

    public string $color = '';

    public string $default_capacity = '10';

    public string $default_duration_minutes = '60';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ClassType::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('class_types', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', Rule::enum(ClassColor::class)],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ];
    }

    /**
     * @return Collection<int, ClassType>
     */
    #[Computed]
    public function classTypes(): Collection
    {
        return ClassType::query()->withCount('slots')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', ClassType::class);

        $this->resetForm();
        $this->color = ClassColor::Sky->value;
        $this->showForm = true;
    }

    public function edit(int $classTypeId): void
    {
        $classType = ClassType::query()->findOrFail($classTypeId);

        $this->authorize('update', $classType);

        $this->resetForm();
        $this->editingId = $classType->id;
        $this->name = $classType->name;
        $this->description = (string) $classType->description;
        $this->color = $classType->color->value;
        $this->default_capacity = (string) $classType->default_capacity;
        $this->default_duration_minutes = (string) $classType->default_duration_minutes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $classType = $this->editingId === null ? new ClassType : ClassType::query()->findOrFail($this->editingId);

        $this->authorize($classType->exists ? 'update' : 'create', $classType->exists ? $classType : ClassType::class);

        $classType->fill($this->validate())->save();

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Class type saved.'));
    }

    public function delete(int $classTypeId): void
    {
        $classType = ClassType::query()->findOrFail($classTypeId);

        $this->authorize('viewAny', ClassType::class);

        $response = Gate::inspect('delete', $classType);

        if ($response->denied()) {
            Flux::toast(variant: 'danger', text: (string) $response->message());

            return;
        }

        $classType->delete();

        Flux::toast(variant: 'success', text: __('Class type deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset('name', 'description', 'color', 'default_capacity', 'default_duration_minutes', 'editingId');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Class types') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The kinds of class on offer, with their defaults.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add class type') }}</flux:button>
    </div>

    @if ($this->classTypes->isEmpty())
        <flux:callout icon="tag" :heading="__('No class types yet')" :text="__('Add one before scheduling classes.')" />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                <flux:table.column>{{ __('Duration') }}</flux:table.column>
                <flux:table.column>{{ __('Slots') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->classTypes as $classType)
                    <flux:table.row :key="$classType->id">
                        <flux:table.cell>
                            <span class="inline-flex items-center gap-2">
                                <span @class(['size-2 rounded-full', $classType->color->dotClass()])></span>
                                {{ $classType->name }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $classType->default_capacity }}</flux:table.cell>
                        <flux:table.cell>{{ __(':minutes min', ['minutes' => $classType->default_duration_minutes]) }}</flux:table.cell>
                        <flux:table.cell>{{ $classType->slots_count }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $classType->id }})" :aria-label="__('Edit')" />

                            @if ($classType->slots_count === 0)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $classType->id }})" wire:confirm="{{ __('Delete this class type?') }}" :aria-label="__('Delete')" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full md:w-[28rem]">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit class type') : __('Add class type') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <flux:select wire:model="color" :label="__('Color')">
                @foreach (ClassColor::cases() as $color)
                    <flux:select.option value="{{ $color->value }}">{{ $color->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input type="number" min="1" max="500" wire:model="default_capacity" :label="__('Default capacity')" />
                <flux:input type="number" min="5" max="480" wire:model="default_duration_minutes" :label="__('Default minutes')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
