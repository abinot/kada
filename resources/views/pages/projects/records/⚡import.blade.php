<?php

use App\Models\ImportBatch;
use App\Models\Project;
use App\Services\RecordImporter;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Import رکوردها')] class extends Component
{
    use WithFileUploads;

    public $team;
    public Project $project;

    public $file;
    public bool $showPreview = false;
    public string $errorMessage = '';

    public array $preview = [];
    public array $records = [];

    public bool $confirmedBackup = false;
    public bool $preserveUuids = true;

    public function mount(Project $project): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);
        $this->project = $project;
    }

    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|max:51200']);

        $this->errorMessage = '';
        $this->showPreview = false;
        $this->preview = [];

        try {
            $importer = app(RecordImporter::class);
            $result = $importer->preview($this->file);

            $this->records = $result['records'];
            unset($result['records']);
            $this->preview = $result;
            $this->showPreview = true;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function import(): void
    {
        if (! $this->confirmedBackup) {
            $this->addError('confirmedBackup', 'قبل از import باید تأیید کنی که بکاپ گرفته‌ای.');
            return;
        }

        if (empty($this->records)) {
            $this->addError('file', 'فایلی بارگذاری نشده.');
            return;
        }

        $batch = app(RecordImporter::class)->import(
            $this->project,
            $this->records,
            $this->file,
            ['preserve_uuids' => $this->preserveUuids],
            $this->preview,
        );

        $this->file = null;
        $this->records = [];
        $this->showPreview = false;
        $this->preview = [];
        $this->confirmedBackup = false;

        Flux::toast(
            variant: 'success',
            text: $batch->record_count.' رکورد با موفقیت import شد.',
        );

        unset($this->importBatches);
    }

    public function cancel(): void
    {
        $this->reset(['file', 'records', 'preview', 'showPreview', 'errorMessage', 'confirmedBackup']);
    }

    public function revert(int $batchId): void
    {
        $batch = ImportBatch::where('project_id', $this->project->id)
            ->whereKey($batchId)
            ->firstOrFail();

        $count = app(RecordImporter::class)->revert($batch);

        unset($this->importBatches, $this->records);
        Flux::toast(variant: 'success', text: "{$count} رکورد برگردانده شد.");
    }

    #[Computed]
    public function importBatches()
    {
        return ImportBatch::where('project_id', $this->project->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }
};
?>

<div class="p-4 md:p-6 max-w-5xl mx-auto space-y-6">
    {{-- هدر --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">Import رکوردها</flux:heading>
            <flux:subheading>وارد کردن رکوردها به «{{ $project->name }}»</flux:subheading>
        </div>

        <flux:button
            :href="route('projects.records.index', ['current_team' => $team, 'project' => $project])"
            variant="ghost"
            icon="arrow-right"
            wire:navigate
        >
            بازگشت
        </flux:button>
    </div>

    {{-- هشدار --}}
    <flux:card class="border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20">
        <div class="flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-6 text-amber-600 flex-shrink-0" />
            <div class="flex-1 space-y-2">
                <flux:heading size="sm" class="text-amber-900 dark:text-amber-100">
                    قبل از Import، بکاپ بگیر
                </flux:heading>
                <flux:text class="text-sm text-amber-800 dark:text-amber-200">
                    اگر import با داده‌های موجود تداخل کرد، می‌توانی از تاریخچه‌ی import ها کل آن batch را برگردانی.
                    ولی احتیاط شرط عقل است.
                </flux:text>
            </div>
        </div>
    </flux:card>

    {{-- انتخاب فایل --}}
    @if (! $showPreview)
        <flux:card class="space-y-4">
            <flux:heading size="sm">انتخاب فایل</flux:heading>
            <flux:text class="text-sm text-neutral-500">
                فرمت‌های پشتیبانی‌شده: JSON، NDJSON، SQLite
            </flux:text>

            <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-8 text-center">
                <flux:icon name="cloud-arrow-up" class="size-12 mx-auto text-zinc-400" />

                <input
                    type="file"
                    wire:model="file"
                    accept=".json,.ndjson,.jsonl,.sqlite,.sqlite3,.db"
                    class="mt-4 mx-auto block text-sm"
                />

                <div wire:loading wire:target="file" class="mt-3 text-sm text-blue-600">
                    در حال پردازش فایل...
                </div>
            </div>

            @error('file')
                <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
            @enderror

            @if ($errorMessage)
                <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-md">
                    <flux:text class="text-sm text-red-700 dark:text-red-300">
                        {{ $errorMessage }}
                    </flux:text>
                </div>
            @endif
        </flux:card>
    @endif

    {{-- پیش‌نمایش --}}
    @if ($showPreview)
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">پیش‌نمایش import</flux:heading>
                    <flux:subheading>فرمت: {{ strtoupper($preview['format'] ?? 'unknown') }}</flux:subheading>
                </div>
                <flux:button variant="ghost" icon="x-mark" wire:click="cancel">
                    انصراف
                </flux:button>
            </div>

            {{-- آمار --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="p-3 rounded-md border border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs text-neutral-500">تعداد رکورد</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($preview['total'] ?? 0) }}</div>
                </div>
                <div class="p-3 rounded-md border border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs text-neutral-500">کلیدهای یکتا</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($preview['unique_keys'] ?? 0) }}</div>
                </div>
                <div class="p-3 rounded-md border border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs text-neutral-500">حداکثر عمق</div>
                    <div class="text-2xl font-bold mt-1">{{ $preview['max_depth'] ?? 0 }}</div>
                </div>
                <div class="p-3 rounded-md border {{ ($preview['duplicate_uuids'] ?? 0) > 0 ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                    <div class="text-xs text-neutral-500">UUID تکراری</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($preview['duplicate_uuids'] ?? 0) }}</div>
                </div>
            </div>

            @if (! empty($preview['has_nested']))
                <div class="flex items-center gap-2 text-sm text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-900/20 p-2 rounded">
                    <flux:icon name="information-circle" class="size-4" />
                    این فایل شامل مقادیر تودرتو (درختی) است. ساختار درخت در import حفظ می‌شود.
                </div>
            @endif

            @if (($preview['duplicate_uuids'] ?? 0) > 0)
                <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 p-2 rounded">
                    <flux:icon name="exclamation-triangle" class="size-4" />
                    {{ $preview['duplicate_uuids'] }} UUID تکراری در فایل وجود دارد. با گزینه‌ی «حفظ UUID»، موارد تکراری UUID جدید می‌گیرند.
                </div>
            @endif

            {{-- جدول کلیدها --}}
            @if (! empty($preview['key_stats']))
                <div>
                    <flux:heading size="sm" class="mb-2">کلیدها و فراوانی</flux:heading>
                    <div class="max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-md">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 sticky top-0">
                                <tr>
                                    <th class="text-start px-3 py-2">کلید</th>
                                    <th class="text-start px-3 py-2">تعداد</th>
                                    <th class="text-start px-3 py-2">درصد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $total = max($preview['total'] ?? 1, 1); @endphp
                                @foreach ($preview['key_stats'] as $key => $count)
                                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                        <td class="px-3 py-1.5">
                                            <code class="text-xs bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">
                                                {{ $key }}
                                            </code>
                                        </td>
                                        <td class="px-3 py-1.5">{{ number_format($count) }}</td>
                                        <td class="px-3 py-1.5 text-neutral-500">
                                            {{ round(($count / $total) * 100, 1) }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- نمونه رکوردها --}}
            @if (! empty($preview['samples']))
                <div>
                    <flux:heading size="sm" class="mb-2">نمونه‌ی رکوردها (۵ تای اول)</flux:heading>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach ($preview['samples'] as $i => $sample)
                            <div class="p-3 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-medium">#{{ $i + 1 }}</span>
                                    @if (! empty($sample['title']))
                                        <span>{{ $sample['title'] }}</span>
                                    @endif
                                    @if (! empty($sample['uuid']))
                                        <code class="text-neutral-500">{{ \Illuminate\Support\Str::limit($sample['uuid'], 16) }}</code>
                                    @endif
                                </div>
                                <pre class="text-[10px] overflow-x-auto bg-zinc-50 dark:bg-zinc-900 p-2 rounded">{{ json_encode($sample['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- تنظیمات --}}
            <div class="space-y-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:switch
                    wire:model="preserveUuids"
                    label="حفظ UUID اصلی"
                    description="اگر خاموش باشد، رکوردهای تکراری UUID جدید می‌گیرند."
                />

             <flux:checkbox
    wire:model.live="confirmedBackup"
    label="تأیید می‌کنم که قبل از import بکاپ گرفته‌ام"
/>

                @error('confirmedBackup')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror
            </div>

            {{-- دکمه‌های اقدام --}}
            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="cancel">انصراف</flux:button>
                <flux:button
                    variant="primary"
                    icon="cloud-arrow-up"
                    wire:click="import"
                    :disabled="! $confirmedBackup"
                >
                    Import {{ number_format($preview['total'] ?? 0) }} رکورد
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- تاریخچه import ها --}}
    @if ($this->importBatches->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:heading size="sm">تاریخچه‌ی import ها</flux:heading>

            @foreach ($this->importBatches as $batch)
                <div class="flex items-center gap-3 p-3 rounded-md border border-zinc-200 dark:border-zinc-700">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-sm">{{ $batch->original_filename }}</span>
                            <flux:badge size="sm" color="{{ match($batch->status) {
                                'completed' => 'green',
                                'reverted' => 'zinc',
                                'failed' => 'red',
                                default => 'amber',
                            } }}">
                                {{ $batch->status }}
                            </flux:badge>
                            <span class="text-xs text-neutral-500">{{ strtoupper($batch->format) }}</span>
                        </div>
                        <div class="text-xs text-neutral-500 mt-1">
                            {{ number_format($batch->record_count) }} رکورد
                            · توسط {{ $batch->user?->name ?? '—' }}
                            · {{ $batch->created_at->diffForHumans() }}
                        </div>
                    </div>

                    @if ($batch->status === 'completed')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-uturn-left"
                            class="text-red-600"
                            wire:click="revert({{ $batch->id }})"
                            wire:confirm="همه‌ی {{ $batch->record_count }} رکورد این import حذف می‌شوند. مطمئنی؟"
                        >
                            برگرداندن
                        </flux:button>
                    @elseif ($batch->status === 'reverted')
                        <flux:text class="text-xs text-neutral-500">
                            برگردانده‌شده {{ $batch->reverted_at?->diffForHumans() }}
                        </flux:text>
                    @endif
                </div>
            @endforeach
        </flux:card>
    @endif
</div>