<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $team;

    public function mount(): void
    {
        $this->team = auth()->user()->currentTeam;
    }

    #[Computed]
    public function projects()
    {
        if (! $this->team) {
            return collect();
        }

        return Project::query()
            ->where('team_id', $this->team->id)
            ->orderByDesc('created_at')
            ->limit(9)
            ->get();
    }
};
?>

<div class="p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="lg">پروژه‌ها</flux:heading>
            <flux:subheading>{{ $this->projects->count() }} پروژه</flux:subheading>
        </div>

        @if ($team)
            <div class="flex gap-2">
          
                <flux:button
                    :href="route('projects.create', ['current_team' => $team])"
                    icon="plus"
                    size="sm"
                    wire:navigate
                >
                    پروژه جدید
                </flux:button>
            </div>
        @endif
    </div>

    @if ($this->projects->isEmpty())
        <div class="text-center py-12">
            <flux:text>هنوز پروژه‌ای ساخته نشده است.</flux:text>
            @if ($team)
                <div class="mt-4">
                    <flux:button
                        :href="route('projects.create', ['current_team' => $team])"
                        variant="filled"
                        wire:navigate
                    >
                        اولین پروژه را بساز
                    </flux:button>
                </div>
            @endif
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->projects as $project)
                <a
                    href="{{ route('projects.show', ['current_team' => $team, 'project' => $project]) }}"
                    wire:navigate
                    class="block rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800 transition"
                >
                    <div class="flex items-start justify-between gap-2">
                        <flux:heading size="sm">{{ $project->name }}</flux:heading>
                        <flux:icon name="arrow-up-right" class="size-4 text-neutral-400 shrink-0" />
                    </div>
                    <flux:text class="mt-2 text-sm text-neutral-500 line-clamp-2">
                        {{ $project->description ?: 'بدون توضیح' }}
                    </flux:text>
                </a>
            @endforeach
        </div>
    @endif
</div>