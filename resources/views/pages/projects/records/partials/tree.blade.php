<div class="space-y-2">
    @foreach ($this->records as $record)
        @php $expanded = in_array($record->id, $expandedRecords); @endphp

        <flux:card class="p-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="toggleRecordExpansion({{ $record->id }})"
                    class="p-1 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                    <flux:icon
                        :name="$expanded ? 'chevron-down' : 'chevron-right'"
                        class="size-4"
                    />
                </button>

                <input
                    type="checkbox"
                    wire:click="toggleRecordSelection({{ $record->id }})"
                    @checked(in_array((string) $record->id, $selectedRecords))
                    class="rounded border-zinc-300 dark:border-zinc-600"
                />

                <a
                    href="{{ route('projects.records.show', [
                        'current_team' => $team,
                        'project' => $project,
                        'record' => $record,
                    ]) }}"
                    wire:navigate
                    class="font-medium hover:underline flex-1"
                >
                    {{ $record->title ?: '#' . $record->id }}
                    
                </a>

                <flux:text class="text-xs text-neutral-500">
                    {{ $record->values->count() }} مقدار
                </flux:text>
            </div>

            @if ($expanded)
                <div class="mt-3 ps-8 pe-2 border-s-2 border-zinc-200 dark:border-zinc-700">
                    @php $roots = $record->rootValues()->with('descendants')->get(); @endphp

                    @if ($roots->isEmpty())
                        <flux:text class="text-sm text-neutral-500">این رکورد مقداری ندارد.</flux:text>
                    @else
                        @include('pages.projects.records.partials.value-tree', [
                            'nodes' => $roots,
                            'level' => 0,
                        ])
                    @endif
                </div>
            @endif
        </flux:card>
    @endforeach
</div>