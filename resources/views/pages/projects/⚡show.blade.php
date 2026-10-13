<?php

use App\Models\Project;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('پروژه')] class extends Component
{
    public $team;
    public Project $project;

    public string $search = '';

    public function mount(Project $project): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        $this->project = $project;
    }

    public function updatingSearch(): void
    {
        // فقط برای ریست صفحه در آینده
    }

    #[Computed]
    public function columns(): array
    {
        return $this->project->keyFrequency(6);
    }

    #[Computed]
    public function records()
    {
        $query = $this->project->records()->with('values');

        if ($this->search !== '') {
            $search = '%'.$this->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('records.title', 'like', $search)
                    ->orWhereHas('values', function ($q) use ($search) {
                        $q->where('value_string', 'like', $search)
                            ->orWhere('value_text', 'like', $search);
                    });
            });
        }

        return $query->orderByDesc('records.updated_at')->limit(10)->get();
    }

    public function deleteRecord(int $recordId): void
    {
        $record = $this->project->records()->whereKey($recordId)->firstOrFail();

        $this->project->records()->detach($record->id);

        if ($record->projects()->count() === 0) {
            $record->forceDelete();
        }

        unset($this->records);
        Flux::toast(variant: 'success', text: 'رکورد حذف شد.');
    }
};
?>

<div class="p-4 md:p-6 space-y-6">
    {{-- هدر پروژه --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
            <flux:subheading>{{ $project->description ?: 'بدون توضیح' }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button
                :href="route('projects.edit', ['current_team' => $team, 'project' => $project])"
                variant="outline"
                icon="pencil"
                size="xs"
                wire:navigate
            >
                ویرایش
            </flux:button>

            <flux:button
                :href="route('projects.records.create', ['current_team' => $team, 'project' => $project])"
                variant="outline"
                icon="plus"
                size="xs"
                wire:navigate
            >
                رکورد جدید
            </flux:button>
        </div>
    </div>

    {{-- آمار کوچک --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <flux:card>
            <flux:text class="text-sm text-neutral-500">کل رکوردها</flux:text>
            <flux:heading size="lg" class="mt-1">{{ $project->records()->count() }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text class="text-sm text-neutral-500">کلیدهای یکتا</flux:text>
            <flux:heading size="lg" class="mt-1">{{ count($this->columns) }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text class="text-sm text-neutral-500">آخرین به‌روزرسانی</flux:text>
            <flux:heading size="lg" class="mt-1 text-sm">{{ $project->updated_at->diffForHumans() }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text class="text-sm text-neutral-500">ساخته‌شده</flux:text>
            <flux:heading size="lg" class="mt-1 text-sm">{{ $project->created_at->diffForHumans() }}</flux:heading>
        </flux:card>
    </div>

    {{-- جدول رکوردها --}}
    <flux:card class="space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <flux:heading size="lg">رکوردها</flux:heading>

            <div class="flex gap-2">
                <flux:input
                    wire:model.live.debounce.400ms="search"
                    placeholder="جستجو..."
                    icon="magnifying-glass"
                    
                    class="w-64"
                />

                <flux:button
                    :href="route('projects.records.index', ['current_team' => $team, 'project' => $project])"
                    variant="primary"
                    color="sky"
                    icon="arrow-left"
                    
                    wire:navigate
                >
                   باز کردن همه  رکورد ها ({{ $project->records()->count() }})
                </flux:button>
            </div>
        </div>

        @if ($this->records->isEmpty())
            <div class="text-center py-12">
                <flux:text>هنوز رکوردی ثبت نشده است.</flux:text>
                <div class="mt-4">
                    <flux:button
                        :href="route('projects.records.create', ['current_team' => $team, 'project' => $project])"
                        variant="filled"
                        wire:navigate
                    >
                        اولین رکورد را بساز
                    </flux:button>
                </div>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>عنوان</flux:table.column>

                    @foreach ($this->columns as $key => $count)
                        <flux:table.column>{{ $key }}</flux:table.column>
                    @endforeach

                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->records as $record)
                        <flux:table.row :key="$record->id">
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
                                    {{ $record->title ?: '#'.$record->id }}
                                </a>
                            </flux:table.cell>

                            @foreach ($this->columns as $key => $count)
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

                            <flux:table.cell>
                                <div class="flex gap-1 justify-end">
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
                                    <flux:modal.trigger :name="'delete-record-'.$record->id">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            class="text-red-600 hover:text-red-700"
                                        />
                                    </flux:modal.trigger>
                                </div>

                                <flux:modal :name="'delete-record-'.$record->id" class="max-w-md">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">حذف رکورد</flux:heading>
                                            <flux:subheading>
                                                آیا مطمئنی می‌خواهی «{{ $record->title ?: '#'.$record->id }}» را حذف کنی؟
                                            </flux:subheading>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="filled">انصراف</flux:button>
                                            </flux:modal.close>
                                            <flux:button
                                                variant="danger"
                                                wire:click="deleteRecord({{ $record->id }})"
                                            >
                                                حذف کن
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
               
    </flux:card>
</div>