<?php

use App\Models\Project;
use App\Models\Record;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('رکورد')] class extends Component
{
    public $team;
    public Project $project;
    public Record $record;

    public function mount(Project $project, Record $record): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        // رکورد باید از طریق pivot به این پروژه وصل باشد
        abort_unless(
            $project->records()->where('records.id', $record->id)->exists(),
            404
        );

        $this->project = $project;
        $this->record = $record;
    }
};
?>

<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $record->title ?: 'رکورد #'.$record->id }}</flux:heading>
            <flux:subheading>در «{{ $project->name }}»</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button
                :href="route('projects.records.index', ['current_team' => $team, 'project' => $project])"
                variant="ghost"
                icon="arrow-right"
                wire:navigate
            >
                بازگشت
            </flux:button>
            <flux:button
                :href="route('projects.records.edit', [
                    'current_team' => $team,
                    'project' => $project,
                    'record' => $record,
                ])"
                variant="primary"
                icon="pencil"
                wire:navigate
            >
                ویرایش
            </flux:button>
        </div>
    </div>

    @if ($record->note)
        <flux:card>
            <flux:heading size="sm">یادداشت</flux:heading>
            <flux:text class="mt-2 whitespace-pre-line">{{ $record->note }}</flux:text>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="sm">مقادیر</flux:heading>

        @php
            $roots = $record->rootValues()->with('descendants')->get();
        @endphp

        @if ($roots->isEmpty())
            <flux:text class="mt-2 text-neutral-500">این رکورد مقداری ندارد.</flux:text>
        @else
            <div class="mt-4">
                @include('pages.projects.records.partials.value-tree', [
                    'nodes' => $roots,
                    'level' => 0,
                ])
            </div>
        @endif
    </flux:card>

    <flux:card class="text-sm text-neutral-500">
        <div class="flex justify-between">
            <span>شناسه: <code>{{ $record->uuid }}</code></span>
            <span>ساخته‌شده: {{ $record->created_at->toDateTimeString() }}</span>
        </div>
    </flux:card>
</div>