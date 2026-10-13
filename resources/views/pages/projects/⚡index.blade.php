<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('پروژه‌ها')] class extends Component
{
    use WithPagination;

    public $team;

    public function mount(): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403, 'تیم فعلی انتخاب نشده است.');
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->where('team_id', $this->team->id)
            ->orderByDesc('created_at')
            ->paginate(70);
    }
};
?>

<div class="p-4 md:p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">پروژه‌ها</flux:heading>
            <flux:subheading>پروژه‌های تیم «{{ $team->name }}»</flux:subheading>
        </div>

        <flux:button :href="route('projects.create', ['current_team' => $team->uuid])" icon="plus">
            پروژه جدید
        </flux:button>
    </div>

    @if ($this->projects->isEmpty())
        <flux:card class="text-center py-16">
            <flux:text>هنوز پروژه‌ای ساخته نشده است.</flux:text>
            <div class="mt-4">
                <flux:button
                    :href="route('projects.create', ['current_team' => $team->uuid])"
                    variant="filled"
                >
                    اولین پروژه را بساز
                </flux:button>
            </div>
        </flux:card>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->projects as $project)
                <flux:card>
                    <flux:heading>
                        <a
                            href="{{ route('projects.show', ['current_team' => $team->uuid, 'project' => $project]) }}"
                            class="hover:underline"
                            wire:navigate
                        >
                            {{ $project->name }}
                        </a>
                    </flux:heading>
                    <flux:text>{{ $project->description ?? 'بدون توضیح' }}</flux:text>

                    <div class="flex gap-2 mt-4">
                        <flux:button
                            :href="route('projects.show', ['current_team' => $team->uuid, 'project' => $project])"
                            size="sm"
                            icon="eye"
                            wire:navigate
                        >
                            مشاهده
                        </flux:button>
                        <flux:button
                            :href="route('projects.edit', ['current_team' => $team->uuid, 'project' => $project])"
                            size="sm"
                            variant="ghost"
                            icon="pencil"
                            wire:navigate
                        >
                            ویرایش
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->projects->links() }}
        </div>
    @endif
</div>