<?php

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('پروژه جدید')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:5000')]
    public string $description = '';

    public function save()
    {
        $this->validate();

        $team = auth()->user()->currentTeam;
        abort_if(! $team, 403);

        $project = Project::create([
            'team_id' => $team->id,
            'name' => $this->name,
            'description' => $this->description,
        ]);

        session()->flash('success', 'پروژه ساخته شد.');

        return $this->redirectRoute('projects.show', [
            'current_team' => $team->uuid,
            'project' => $project,
        ], navigate: true);
    }

    public function with(): array
    {
        return ['team' => auth()->user()->currentTeam];
    }
};
?>

<div class="p-4 md:p-6 max-w-2xl mx-auto">
    <flux:card>
        <flux:heading size="lg">ساخت پروژه جدید</flux:heading>

        <form wire:submit="save" class="space-y-6 mt-6">
            <flux:input
                wire:model="name"
                label="نام پروژه"
                placeholder="مثلاً: فهرست نیازمندان"
                required
                autofocus
            />

            <flux:textarea
                wire:model="description"
                label="توضیحات"
                rows="4"
                placeholder="توضیحات اختیاری"
            />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    ذخیره
                </flux:button>
                <flux:button
                    :href="route('dashboard', ['current_team' => $team->uuid])"
                    variant="ghost"
                    wire:navigate
                >
                    انصراف
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>