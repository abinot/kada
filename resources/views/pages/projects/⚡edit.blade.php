<?php

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('ویرایش پروژه')] class extends Component
{
    public $team;
    public Project $project;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:5000')]
    public string $description = '';

    public function mount(Project $project): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
    }

    public function save()
    {
        $this->validate();

        $this->project->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        session()->flash('success', 'پروژه به‌روزرسانی شد.');

        return $this->redirectRoute('projects.show', [
            'current_team' => $this->team,
            'project' => $this->project,
        ], navigate: true);
    }
};
?>

<div class="p-4 md:p-6 max-w-2xl mx-auto">
    <flux:card>
        <flux:heading size="lg">ویرایش پروژه</flux:heading>

        <form wire:submit="save" class="space-y-6 mt-6">
            <flux:input wire:model="name" label="نام پروژه" required />
            <flux:textarea wire:model="description" label="توضیحات" rows="4" />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    ذخیره تغییرات
                </flux:button>
                <flux:button
                    :href="route('projects.show', ['current_team' => $team, 'project' => $project])"
                    variant="ghost"
                    wire:navigate
                >
                    انصراف
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>