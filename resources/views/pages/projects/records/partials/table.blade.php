<flux:card class="overflow-x-auto">
    <flux:table>
        <flux:table.columns>
            <flux:table.column class="w-10">
                <input
                    type="checkbox"
                    wire:model.live="selectAll"
                    class="rounded border-zinc-300 dark:border-zinc-600"
                />
            </flux:table.column>

            {{-- مرتب‌سازی با id --}}
            <flux:table.column>
                <button
                    type="button"
                    wire:click="$set('sortBy', 'id')"
                    class="flex items-center gap-1 hover:text-blue-600"
                >
                    #
                    @if ($sortBy === 'id')
                        <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                    @endif
                </button>
            </flux:table.column>

            {{-- عنوان --}}
            <flux:table.column>
                <button
                    type="button"
                    wire:click="$set('sortBy', 'title')"
                    class="flex items-center gap-1 hover:text-blue-600"
                >
                    عنوان
                    @if ($sortBy === 'title')
                        <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                    @endif
                </button>
            </flux:table.column>

            {{-- ستون‌های داینامیک --}}
            @foreach ($selectedColumns as $key)
                <flux:table.column>
                    <div class="flex items-center gap-1">
                        <input
                            type="checkbox"
                            wire:click="toggleColumnSelection('{{ $key }}')"
                            @checked(in_array($key, $selectedExportColumns))
                            class="rounded border-zinc-300 dark:border-zinc-600"
                            title="برای export انتخاب کن"
                        />
                        <button
                            type="button"
                            wire:click="$set('sortBy', '{{ $key }}')"
                            class="flex items-center gap-1 hover:text-blue-600"
                        >
                            {{ $key }}
                            @if ($sortBy === $key)
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                            @endif
                        </button>
                    </div>
                </flux:table.column>
            @endforeach

            {{-- created_at --}}
            <flux:table.column>
                <button
                    type="button"
                    wire:click="$set('sortBy', 'created_at')"
                    class="flex items-center gap-1 hover:text-blue-600"
                >
                    ایجاد
                    @if ($sortBy === 'created_at')
                        <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                    @endif
                </button>
            </flux:table.column>

            {{-- updated_at --}}
            <flux:table.column>
                <button
                    type="button"
                    wire:click="$set('sortBy', 'updated_at')"
                    class="flex items-center gap-1 hover:text-blue-600"
                >
                    به‌روزرسانی
                    @if ($sortBy === 'updated_at')
                        <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                    @endif
                </button>
            </flux:table.column>

            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->records as $record)
                @php $isSelected = in_array((string) $record->id, $selectedRecords); @endphp

                <flux:table.row :key="$record->id" class="{{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                    <flux:table.cell>
                        <input
                            type="checkbox"
                            wire:click="toggleRecordSelection({{ $record->id }})"
                            @checked($isSelected)
                            class="rounded border-zinc-300 dark:border-zinc-600"
                        />
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-neutral-500">
                        {{ $record->id }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <a
                            href="{{ route('projects.records.show', [
                                'current_team' => $team,
                                'project' => $project,
                                'record' => $record,
                            ]) }}"
                            wire:navigate
                            class="font-medium hover:underline"
                        >
                            {{ $record->title ?: '—' }}
                        </a>
                    </flux:table.cell>

                    @foreach ($selectedColumns as $key)
                        <flux:table.cell>
                            @php $value = $record->getValue($key); @endphp

                            @if (is_null($value) || $value === '')
                                <span class="text-neutral-400">—</span>
                            @elseif (is_bool($value))
                                @if ($value)
                                    <flux:badge color="green" size="sm">بله</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">خیر</flux:badge>
                                @endif
                            @elseif ($value instanceof \DateTimeInterface)
                                {{ $value->toDateString() }}
                            @else
                                <span class="line-clamp-1">{{ $value }}</span>
                            @endif
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell class="text-xs text-neutral-500">
                        {{ $record->created_at->toDateString() }}
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-neutral-500">
                        {{ $record->updated_at->diffForHumans() }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-1 justify-end">
                            @if ($showTrash)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-path"
                                    wire:click="toggleRecordSelection({{ $record->id }})"
                                    title="انتخاب برای بازیابی"
                                />
                            @else
                                <flux:button
                                    :href="route('projects.records.show', [
                                        'current_team' => $team,
                                        'project' => $project,
                                        'record' => $record,
                                    ])"
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                    wire:navigate
                                />
                                <flux:button
                                    :href="route('projects.records.edit', [
                                        'current_team' => $team,
                                        'project' => $project,
                                        'record' => $record,
                                    ])"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    wire:navigate
                                />
                                <button
                                    type="button"
                                    wire:click="$set('selectedRecords', ['{{ $record->id }}'])"
                                    class="p-1.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 text-red-600"
                                    title="حذف"
                                    wire:confirm="آیا مطمئنی؟"
                                >
                                    <flux:icon name="trash" class="size-4" />
                                </button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</flux:card>