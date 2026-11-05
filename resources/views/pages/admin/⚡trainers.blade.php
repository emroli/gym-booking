<?php

use App\Models\Trainer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Trainers')] class extends Component {
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public string $bio = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Trainer::class);
    }

    /**
     * @return Collection<int, Trainer>
     */
    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::query()->withCount('slots')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Trainer::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $trainerId): void
    {
        $trainer = Trainer::query()->findOrFail($trainerId);

        $this->authorize('update', $trainer);

        $this->resetForm();
        $this->editingId = $trainer->id;
        $this->name = $trainer->name;
        $this->bio = (string) $trainer->bio;
        $this->showForm = true;
    }

    public function save(): void
    {
        $trainer = $this->editingId === null ? new Trainer : Trainer::query()->findOrFail($this->editingId);

        $this->authorize($trainer->exists ? 'update' : 'create', $trainer->exists ? $trainer : Trainer::class);

        $trainer->fill($this->validate())->save();

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Trainer saved.'));
    }

    public function delete(int $trainerId): void
    {
        $trainer = Trainer::query()->findOrFail($trainerId);

        $this->authorize('viewAny', Trainer::class);

        $response = Gate::inspect('delete', $trainer);

        if ($response->denied()) {
            Flux::toast(variant: 'danger', text: (string) $response->message());

            return;
        }

        $trainer->delete();

        Flux::toast(variant: 'success', text: __('Trainer deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset('name', 'bio', 'editingId');
        $this->resetValidation();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Trainers') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The people who lead your classes.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add trainer') }}</flux:button>
    </div>

    @if ($this->trainers->isEmpty())
        <flux:callout icon="academic-cap" :heading="__('No trainers yet')" :text="__('Add one before scheduling classes.')" />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Bio') }}</flux:table.column>
                <flux:table.column>{{ __('Slots') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->trainers as $trainer)
                    <flux:table.row :key="$trainer->id">
                        <flux:table.cell>{{ $trainer->name }}</flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate">{{ $trainer->bio }}</flux:table.cell>
                        <flux:table.cell>{{ $trainer->slots_count }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $trainer->id }})" :aria-label="__('Edit')" />

                            @if ($trainer->slots_count === 0)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $trainer->id }})" wire:confirm="{{ __('Delete this trainer?') }}" :aria-label="__('Delete')" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full md:w-[28rem]">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit trainer') : __('Add trainer') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" />
            <flux:textarea wire:model="bio" :label="__('Bio')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
