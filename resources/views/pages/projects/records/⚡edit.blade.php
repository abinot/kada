<?php

use App\Models\Project;
use App\Models\Record;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('ویرایش رکورد')] class extends Component
{
    public $team;
    public Project $project;
    public Record $record;

    public string $title = '';
    public string $note = '';

    public array $rows = [];

    public function mount(Project $project, Record $record): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        // چک کن رکورد به این پروژه وصل است
        abort_unless(
            $project->records()->where('records.id', $record->id)->exists(),
            404
        );

        $this->project = $project;
        $this->record = $record;
        $this->title = $record->title ?? '';
        $this->note = $record->note ?? '';

        // پر کردن rows از flatData
        foreach ($record->flatData as $path => $value) {
            $type = 'string';
            if (is_int($value)) $type = 'integer';
            elseif (is_float($value)) $type = 'decimal';
            elseif (is_bool($value)) $type = 'boolean';
            elseif ($value instanceof \DateTimeInterface) $type = 'datetime';

            $this->rows[] = [
                'key' => $path,
                'value' => $value instanceof \DateTimeInterface
                    ? $value->format('Y-m-d\TH:i')
                    : $value,
                'type' => $type,
            ];
        }

        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['key' => '', 'value' => '', 'type' => 'string'];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'rows.*.key' => 'nullable|string|max:100|regex:/^[a-z][a-z0-9_.]*$/',
            'rows.*.value' => 'nullable',
        ]);

        $this->record->update([
            'title' => $this->title ?: null,
            'note' => $this->note ?: null,
        ]);

        // همه‌ی مقادیر قبلی را پاک کن
        $this->record->values()->delete();

        // ساخت درخت جدید
        $tree = [];
        foreach ($this->rows as $row) {
            if ($row['key'] === '') continue;
            $tree[$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        if (! empty($tree)) {
            $this->record->syncValues($tree);
        }

        Flux::toast(variant: 'success', text: 'رکورد به‌روزرسانی شد.');

        $this->redirectRoute('projects.records.show', [
            'current_team' => $this->team,
            'project' => $this->project,
            'record' => $this->record,
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
        <flux:heading size="xl">ویرایش رکورد</flux:heading>
        <flux:subheading>{{ $record->title ?: '#'.$record->id }} در «{{ $project->name }}»</flux:subheading>
    </div>

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
                :href="route('projects.records.show', [
                    'current_team' => $team,
                    'project' => $project,
                    'record' => $record,
                ])"
                variant="ghost"
                wire:navigate
            >
                انصراف
            </flux:button>
            <flux:button type="submit" variant="primary">ذخیره تغییرات</flux:button>
        </div>
    </form>
</div>