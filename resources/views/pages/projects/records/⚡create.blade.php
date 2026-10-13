<?php

use App\Models\Project;
use App\Models\Record;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('رکورد جدید')] class extends Component
{
    public $team;
    public Project $project;

    public string $title = '';
    public string $note = '';

    /**
     * @var array<int, array{key: string, value: mixed, type: string}>
     */
    public array $rows = [];

    public function mount(Project $project): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        $this->project = $project;
        $this->addRow();
    }

    #[Computed]
    public function suggestedKeys(): array
    {
        return $this->project->keyFrequency(12);
    }

    public function addRow(): void
    {
        $this->rows[] = [
            'key' => '',
            'value' => '',
            'type' => 'string',
        ];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function pickKey(string $key): void
    {
        foreach ($this->rows as $i => $row) {
            if ($row['key'] === '') {
                $this->rows[$i]['key'] = $key;
                return;
            }
        }

        $this->rows[] = ['key' => $key, 'value' => '', 'type' => 'string'];
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'rows.*.key' => 'nullable|string|max:255', // |regex:/^[a-z][a-z0-9_.]*$/
            'rows.*.value' => 'nullable',
        ]);

        $record = Record::create([
            'title' => $this->title ?: null,
            'note' => $this->note ?: null,
        ]);

        $record->projects()->attach($this->project->id);

        $tree = [];
        foreach ($this->rows as $row) {
            if ($row['key'] === '') continue;
            $tree[$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        if (! empty($tree)) {
            $record->syncValues($tree);
        }

        Flux::toast(variant: 'success', text: 'رکورد ساخته شد.');

        $this->redirectRoute('projects.records.show', [
            'current_team' => $this->team,
            'project' => $this->project,
            'record' => $record,
        ], navigate: true);
    }

    protected function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => $value === '' ? null : (int) $value,
            'decimal' => $value === '' ? null : (float) $value,
            'boolean' => (bool) $value,
            default => $value,
        };
    }
};
?>

<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-6">
    <div>
        <flux:heading size="xl">رکورد جدید</flux:heading>
        <flux:subheading>افزودن به «{{ $project->name }}»</flux:subheading>
    </div>

    @if (! empty($this->suggestedKeys))
        <flux:card>
            <flux:heading size="sm">کلیدهای پرتکرار در این پروژه</flux:heading>
            <flux:text class="text-sm text-neutral-500 mt-1">
                روی هر کلید بزن تا در اولین ردیف خالی اضافه شود.
            </flux:text>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach ($this->suggestedKeys as $key => $count)
                    <flux:button
                        type="button"
                        size="sm"
                        variant="outline"
                        wire:click="pickKey('{{ $key }}')"
                    >
                        {{ $key }}
                        <span class="text-xs text-neutral-400">({{ $count }})</span>
                    </flux:button>
                @endforeach
            </div>
        </flux:card>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:input wire:model="title" label="عنوان (اختیاری)" placeholder="مثلاً: رضا بهرامیان" />
            <flux:textarea wire:model="note" label="یادداشت (اختیاری)" rows="2" />
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="sm">مقادیر</flux:heading>

            @foreach ($rows as $i => $row)
                <div class="grid grid-cols-12 gap-2 items-start" wire:key="row-{{ $i }}">
                    <div class="col-span-4">
                        <flux:input wire:model="rows.{{ $i }}.key" placeholder="name, phone, ..." />
                        @error("rows.{$i}.key")
                            <flux:text class="text-sm text-red-600 mt-1">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="col-span-3">
                        <flux:select wire:model.live="rows.{{ $i }}.type">
                            <flux:select.option value="string">متن</flux:select.option>
                            <flux:select.option value="text">متن بلند</flux:select.option>
                            <flux:select.option value="integer">عدد</flux:select.option>
                            <flux:select.option value="decimal">اعشاری</flux:select.option>
                            <flux:select.option value="boolean">بله/خیر</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="col-span-4">
                        @if ($row['type'] === 'boolean')
                            <flux:switch wire:model="rows.{{ $i }}.value" />
                        @elseif ($row['type'] === 'text')
                            <flux:textarea wire:model="rows.{{ $i }}.value" rows="2" />
                        @else
                            <flux:input
                                wire:model="rows.{{ $i }}.value"
                                type="{{ match ($row['type']) {
                                    'integer', 'decimal' => 'number',
                                    default => 'text',
                                } }}"
                            />
                        @endif
                    </div>

                    <div class="col-span-1">
                        <flux:button
                            type="button"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removeRow({{ $i }})"
                            class="text-red-600"
                        />
                    </div>
                </div>
            @endforeach

            <flux:button type="button" variant="outline" icon="plus" wire:click="addRow">
                افزودن مقدار
            </flux:button>
        </flux:card>

        <div class="flex gap-2 justify-end">
            <flux:button
                :href="route('projects.records.index', ['current_team' => $team, 'project' => $project])"
                variant="ghost"
                wire:navigate
            >
                انصراف
            </flux:button>
            <flux:button type="submit" variant="primary">ذخیره</flux:button>
        </div>
    </form>
</div>