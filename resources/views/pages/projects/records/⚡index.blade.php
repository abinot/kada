<?php

use App\Models\ImportBatch;
use App\Models\Project;
use App\Models\Record;
use App\Services\RecordExporter;
use App\Services\RecordImporter;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('رکوردها')] class extends Component
{
    use WithPagination;

    public $team;
    public Project $project;

    // ---- جستجو ----
    #[Url]
    public string $search = '';

    #[Url]
    public string $searchMode = 'simple';

    // ---- مرتب‌سازی ----
    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDir = 'desc';

    #[Url]
    public string $sortType = 'auto';

    // ---- نما ----
    #[Url]
    public string $viewMode = 'table';

    #[Url]
    public bool $showTrash = false;

    // ---- ستون‌ها ----
    #[Url(as: 'cols')]
    public array $selectedColumns = [];

    public array $availableKeys = [];
    public string $columnSearch = '';

    // ---- انتخاب رکورد ----
    public array $selectedRecords = [];
    public bool $selectAll = false;

    // ---- انتخاب ستون برای export ----
    public array $selectedExportColumns = [];

    // ---- فیلترها ----
    public array $filters = [];
    public bool $showFilterPanel = false;
    public string $newFilterKey = '';
    public string $newFilterOperator = 'contains';
    public string $newFilterValue = '';

    // ---- درخت ----
    public array $expandedRecords = [];

    // ---- ویرایش گروهی ----
    public bool $showBulkEditModal = false;
    public array $bulkEditRows = [];

    // ================= Lifecycle =================

    public function mount(Project $project): void
    {
        $this->team = auth()->user()->currentTeam;
        abort_if(! $this->team, 403);
        abort_if($project->team_id !== $this->team->id, 404);

        $this->project = $project;

        $this->availableKeys = array_keys($this->project->keyFrequency(500));

        if (empty($this->selectedColumns)) {
            $this->selectedColumns = array_slice($this->availableKeys, 0, 10);
        }

        $this->selectedColumns = array_values(
            array_intersect($this->selectedColumns, $this->availableKeys)
        );
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSearchMode(): void { $this->resetPage(); }
    public function updatingSortBy(): void { $this->resetPage(); }
    public function updatingSortDir(): void { $this->resetPage(); }
    public function updatingShowTrash(): void { $this->resetPage(); $this->clearSelection(); }

    // ================= Records =================

    #[Computed]
    public function records()
    {
        return $this->buildQuery()->paginate(25);
    }

    protected function buildQuery()
    {
        $query = $this->project->records()
            ->with(['values.children', 'values.descendants']);

        if ($this->showTrash) {
            $query->onlyTrashed();
        }

        if ($this->search !== '') {
            $this->applySearch($query);
        }

        foreach ($this->filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        $this->applySorting($query);

        return $query;
    }

    protected function applySearch($query): void
    {
        $search = trim($this->search);

        if ($this->searchMode === 'simple') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('records.title', 'like', $term)
                  ->orWhere('records.note', 'like', $term)
                  ->orWhereHas('values', function ($q) use ($term) {
                      $q->where('value_string', 'like', $term)
                        ->orWhere('value_text', 'like', $term);
                  });
            });
            return;
        }

        $terms = preg_split('/\s+/', $search);

        foreach ($terms as $term) {
            if ($term === '') continue;

            if (str_contains($term, ':')) {
                [$key, $val] = explode(':', $term, 2);
                $query->whereHas('values', function ($q) use ($key, $val) {
                    $q->where('key', 'like', '%'.$key.'%')
                      ->where(function ($q) use ($val) {
                          $q->where('value_string', 'like', '%'.$val.'%')
                            ->orWhere('value_text', 'like', '%'.$val.'%');
                      });
                });
            } else {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('records.title', 'like', $like)
                      ->orWhere('records.note', 'like', $like)
                      ->orWhereHas('values', function ($q) use ($like) {
                          $q->where('value_string', 'like', $like)
                            ->orWhere('value_text', 'like', $like);
                      });
                });
            }
        }
    }

    protected function applyFilter($query, array $filter): void
    {
        $key = $filter['key'] ?? null;
        $op = $filter['operator'] ?? 'contains';
        $value = $filter['value'] ?? null;

        if (! $key || $value === null || $value === '') return;

        $query->whereHas('values', function ($q) use ($key, $op, $value) {
            $q->where('key', $key);

            match ($op) {
                'contains' => $q->where(fn ($q) => $q
                    ->where('value_string', 'like', '%'.$value.'%')
                    ->orWhere('value_text', 'like', '%'.$value.'%')),

                'equals' => $q->where(fn ($q) => $q
                    ->where('value_string', $value)
                    ->orWhere('value_text', $value)
                    ->orWhere('value_number', $value)),

                'gt' => $q->where('value_number', '>', $value),
                'gte' => $q->where('value_number', '>=', $value),
                'lt' => $q->where('value_number', '<', $value),
                'lte' => $q->where('value_number', '<=', $value),

                'empty' => $q->whereNull('value_string')
                    ->whereNull('value_number')
                    ->whereNull('value_text'),

                'not_empty' => $q->where(fn ($q) => $q
                    ->whereNotNull('value_string')
                    ->orWhereNotNull('value_number')
                    ->orWhereNotNull('value_text')),

                default => null,
            };
        });
    }

    protected function applySorting($query): void
    {
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        if (in_array($this->sortBy, ['id', 'title', 'created_at', 'updated_at'])) {
            $col = $this->sortBy === 'id' ? 'records.id' : 'records.'.$this->sortBy;
            $query->orderBy($col, $dir);
            return;
        }

        $key = $this->sortBy;
        $column = $this->sortType === 'numeric' ? 'value_number' : 'value_string';

        $query->leftJoin('record_values as sort_rv', function ($join) use ($key) {
            $join->on('sort_rv.record_id', '=', 'records.id')
                 ->where('sort_rv.key', $key)
                 ->whereNull('sort_rv.parent_id');
        })
        ->orderBy('sort_rv.'.$column, $dir)
        ->select('records.*');
    }

    // ================= Columns =================

    public function toggleColumn(string $key): void
    {
        if (in_array($key, $this->selectedColumns)) {
            $this->selectedColumns = array_values(
                array_diff($this->selectedColumns, [$key])
            );
        } else {
            $this->selectedColumns[] = $key;
        }
        unset($this->records);
    }

    public function selectTopColumns(int $n = 10): void
    {
        $this->selectedColumns = array_slice($this->availableKeys, 0, $n);
        unset($this->records);
    }

    public function clearColumns(): void
    {
        $this->selectedColumns = [];
        unset($this->records);
    }

    #[Computed]
    public function filteredAvailableKeys(): array
    {
        if ($this->columnSearch === '') {
            return $this->availableKeys;
        }
        $s = strtolower($this->columnSearch);
        return array_values(array_filter(
            $this->availableKeys,
            fn ($k) => str_contains(strtolower($k), $s)
        ));
    }

    // ================= Selection =================

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedRecords = $this->records->pluck('id')
                ->map(fn ($id) => (string) $id)->all();
        } else {
            $this->clearSelection();
        }
    }

    public function toggleRecordSelection(int $recordId): void
    {
        $id = (string) $recordId;
        if (in_array($id, $this->selectedRecords)) {
            $this->selectedRecords = array_values(
                array_diff($this->selectedRecords, [$id])
            );
        } else {
            $this->selectedRecords[] = $id;
        }
        $this->selectAll = false;
    }

    public function clearSelection(): void
    {
        $this->selectedRecords = [];
        $this->selectAll = false;
    }

    // ================= Column export selection =================

    public function toggleColumnSelection(string $key): void
    {
        if (in_array($key, $this->selectedExportColumns)) {
            $this->selectedExportColumns = array_values(
                array_diff($this->selectedExportColumns, [$key])
            );
        } else {
            $this->selectedExportColumns[] = $key;
        }
    }

    public function clearExportColumns(): void
    {
        $this->selectedExportColumns = [];
    }

    // ================= Bulk actions =================

    public function bulkDelete(): void
    {
        if (empty($this->selectedRecords)) return;

        $count = count($this->selectedRecords);

        foreach ($this->selectedRecords as $id) {
            $record = $this->project->records()->whereKey($id)->first();
            if (! $record) continue;
            $this->project->records()->detach($record->id);
            if ($record->projects()->count() === 0) {
                $record->delete();
            }
        }

        $this->clearSelection();
        unset($this->records);
        Flux::toast(variant: 'success', text: "{$count} رکورد به سطل آشغال رفت.");
    }

    public function bulkRestore(): void
    {
        if (empty($this->selectedRecords)) return;
        $count = count($this->selectedRecords);

        Record::onlyTrashed()->whereIn('id', $this->selectedRecords)->restore();

        $this->clearSelection();
        unset($this->records);
        Flux::toast(variant: 'success', text: "{$count} رکورد بازیابی شد.");
    }

    public function bulkForceDelete(): void
    {
        if (empty($this->selectedRecords)) return;
        $count = count($this->selectedRecords);

        Record::onlyTrashed()->whereIn('id', $this->selectedRecords)->forceDelete();

        $this->clearSelection();
        unset($this->records);
        Flux::toast(variant: 'success', text: "{$count} رکورد برای همیشه حذف شد.");
    }

    public function openBulkEdit(): void
    {
        if (empty($this->selectedRecords)) return;
        $this->bulkEditRows = [['key' => '', 'value' => '', 'type' => 'string']];
        $this->showBulkEditModal = true;
    }

    public function addBulkEditRow(): void
    {
        $this->bulkEditRows[] = ['key' => '', 'value' => '', 'type' => 'string'];
    }

    public function removeBulkEditRow(int $i): void
    {
        unset($this->bulkEditRows[$i]);
        $this->bulkEditRows = array_values($this->bulkEditRows);
    }

    public function applyBulkEdit(): void
    {
        foreach ($this->selectedRecords as $id) {
            $record = $this->project->records()->whereKey($id)->first();
            if (! $record) continue;

            foreach ($this->bulkEditRows as $row) {
                if ($row['key'] === '' || $row['value'] === '') continue;

                $value = match ($row['type']) {
                    'integer' => (int) $row['value'],
                    'decimal' => (float) $row['value'],
                    'boolean' => (bool) $row['value'],
                    default => $row['value'],
                };

                $record->setValue($row['key'], $value);
            }
        }

        $this->showBulkEditModal = false;
        $this->bulkEditRows = [];
        $this->clearSelection();
        unset($this->records);

        Flux::toast(variant: 'success', text: 'ویرایش گروهی اعمال شد.');
    }

    // ================= Filters =================

    public function addFilter(): void
    {
        if (! $this->newFilterKey) return;

        $this->filters[] = [
            'key' => $this->newFilterKey,
            'operator' => $this->newFilterOperator,
            'value' => $this->newFilterValue,
        ];

        $this->newFilterKey = '';
        $this->newFilterValue = '';
        $this->newFilterOperator = 'contains';

        $this->resetPage();
    }

    public function removeFilter(int $i): void
    {
        unset($this->filters[$i]);
        $this->filters = array_values($this->filters);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->resetPage();
    }

    // ================= Tree view =================

    public function toggleRecordExpansion(int $recordId): void
    {
        if (in_array($recordId, $this->expandedRecords)) {
            $this->expandedRecords = array_values(
                array_diff($this->expandedRecords, [$recordId])
            );
        } else {
            $this->expandedRecords[] = $recordId;
        }
    }

    // ================= Export =================

    public function exportProject(string $format = 'json')
    {
        $columns = ! empty($this->selectedExportColumns)
            ? $this->selectedExportColumns
            : $this->selectedColumns;

        return app(RecordExporter::class)->exportProject(
            $this->project,
            $columns,
            $format,
        );
    }

    public function exportSelected(string $format = 'json')
    {
        $columns = ! empty($this->selectedExportColumns)
            ? $this->selectedExportColumns
            : $this->selectedColumns;

        if (! empty($this->selectedRecords)) {
            $records = $this->project->records()
                ->whereIn('records.id', $this->selectedRecords)
                ->with('values')
                ->get();

            return app(RecordExporter::class)->exportSelection(
                $records,
                $columns,
                $format,
                'selected-'.$this->project->uuid,
            );
        }

        return app(RecordExporter::class)->exportProject(
            $this->project,
            $columns,
            $format,
        );
    }

    public function exportWorkspace(string $format = 'json')
    {
        return app(RecordExporter::class)->exportTeam(
            $this->team,
            [],
            $format,
        );
    }

    // ================= Import batches =================

    #[Computed]
    public function importBatches()
    {
        return ImportBatch::where('project_id', $this->project->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function revertBatch(int $batchId): void
    {
        $batch = ImportBatch::where('project_id', $this->project->id)
            ->whereKey($batchId)
            ->firstOrFail();

        $count = app(RecordImporter::class)->revert($batch);

        unset($this->records, $this->importBatches);
        Flux::toast(variant: 'success', text: "{$count} رکورد برگردانده شد.");
    }
};
?>

<div class="p-4 md:p-6 space-y-4">
    {{-- ============ HEADER ============ --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">
                @if ($showTrash) سطل آشغال @else رکوردها @endif
            </flux:heading>
            <flux:subheading>{{ $this->records->total() }} رکورد در «{{ $project->name }}»</flux:subheading>
        </div>

        <div class="flex gap-2 flex-wrap">
            <flux:button
                :href="route('projects.show', ['current_team' => $team, 'project' => $project])"
                variant="ghost"
                icon="arrow-right"
                wire:navigate
            >
                بازگشت
            </flux:button>

            @unless ($showTrash)
                <flux:button
                    :href="route('projects.records.create', ['current_team' => $team, 'project' => $project])"
                    icon="plus"
                    variant="primary"
                    wire:navigate
                >
                    رکورد جدید
                </flux:button>
            @endunless
        </div>
    </div>

    {{-- ============ TOOLBAR ============ --}}
    <flux:card class="p-3">
        <div class="flex flex-wrap items-center gap-2">
            {{-- View mode --}}
            <div class="flex rounded-md border border-zinc-200 dark:border-zinc-700">
                <button
                    type="button"
                    wire:click="$set('viewMode', 'table')"
                    class="px-3 py-1.5 text-sm rounded-l-md {{ $viewMode === 'table' ? 'bg-zinc-200 dark:bg-zinc-500 font-medium' : '' }}"
                >جدول</button>
                <button
                    type="button"
                    wire:click="$set('viewMode', 'tree')"
                    class="px-3 py-1.5 text-sm rounded-r-md border-l border-zinc-200 dark:border-zinc-700 {{ $viewMode === 'tree' ? 'bg-zinc-200 dark:bg-zinc-500 font-medium' : '' }}"
                >درخت</button>
            </div>

            <flux:separator vertical class="h-6" />

            {{-- Trash toggle --}}
            <button
                type="button"
                wire:click="$toggle('showTrash')"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border {{ $showTrash ? 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700 text-red-700 dark:text-red-300' : 'border-zinc-200 dark:border-zinc-700' }}"
            >
                <flux:icon name="trash" class="size-4" />
                سطل آشغال
            </button>

            <flux:separator vertical class="h-6" />

            {{-- Column picker --}}
            <flux:modal.trigger name="column-picker">
                <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border border-zinc-200 dark:border-zinc-700">
                    <flux:icon name="view-columns" class="size-4" />
                    ستون‌ها ({{ count($selectedColumns) }})
                </button>
            </flux:modal.trigger>

            {{-- Filter --}}
            <button
                type="button"
                wire:click="$toggle('showFilterPanel')"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border {{ !empty($filters) ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300' : 'border-zinc-200 dark:border-zinc-700' }}"
            >
                <flux:icon name="funnel" class="size-4" />
                فیلترها
                @if (!empty($filters))
                    <span class="rounded-full bg-blue-600 text-white text-xs px-1.5">{{ count($filters) }}</span>
                @endif
            </button>

            <flux:spacer />

            {{-- Import --}}
            <flux:button
                :href="route('projects.records.import', ['current_team' => $team, 'project' => $project])"
                size="sm"
                variant="outline"
                icon="cloud-arrow-up"
                wire:navigate
            >
                Import
            </flux:button>

            {{-- Export --}}
            <flux:modal.trigger name="export-modal">
                <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border border-zinc-200 dark:border-zinc-700">
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    خروجی
                </button>
            </flux:modal.trigger>
        </div>
    </flux:card>

    {{-- ============ SEARCH ============ --}}
    <flux:card class="p-3">
        <div class="flex gap-2 items-center">
            <flux:input
                wire:model.live.debounce.400ms="search"
                placeholder="{{ $searchMode === 'advanced' ? 'مثال: name:علی phone:0912' : 'جستجو در رکوردها...' }}"
                icon="magnifying-glass"
                class="flex-1"
            />

            <button
                type="button"
                wire:click="$set('searchMode', '{{ $searchMode === 'simple' ? 'advanced' : 'simple' }}')"
                class="px-3 py-1.5 text-sm rounded-md border {{ $searchMode === 'advanced' ? 'bg-violet-100 dark:bg-violet-900/30 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300' : 'border-zinc-200 dark:border-zinc-700' }}"
            >
                {{ $searchMode === 'advanced' ? 'جستجوی پیشرفته' : 'جستجوی ساده' }}
            </button>
        </div>

        @if ($searchMode === 'advanced')
            <flux:text class="text-xs text-neutral-500 mt-2">
                پشتیبانی از <code class="px-1 bg-neutral-100 dark:bg-neutral-800 rounded">key:value</code> — چند عبارت با فاصله = AND.
            </flux:text>
        @endif
    </flux:card>

    {{-- ============ FILTER PANEL ============ --}}
    @if ($showFilterPanel)
        <flux:card class="p-4 space-y-3">
            <flux:heading size="sm">فیلترها</flux:heading>

            @foreach ($filters as $i => $filter)
                <div class="flex gap-2 items-center p-2 bg-zinc-50 dark:bg-zinc-800 rounded-md" wire:key="filter-{{ $i }}">
                    <code class="text-xs px-2 py-1 bg-white dark:bg-zinc-900 rounded">{{ $filter['key'] }}</code>
                    <span class="text-xs text-neutral-500">
                        {{ match($filter['operator']) {
                            'contains' => 'شامل',
                            'equals' => 'برابر با',
                            'gt' => 'بزرگتر از',
                            'gte' => 'بزرگتر یا مساوی',
                            'lt' => 'کوچکتر از',
                            'lte' => 'کوچکتر یا مساوی',
                            'empty' => 'خالی',
                            'not_empty' => 'غیرخالی',
                            default => $filter['operator'],
                        } }}
                    </span>
                    <code class="text-xs px-2 py-1 bg-white dark:bg-zinc-900 rounded">{{ $filter['value'] }}</code>
                    <flux:spacer />
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeFilter({{ $i }})" class="text-red-600" />
                </div>
            @endforeach

            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                <flux:select wire:model="newFilterKey" placeholder="کلید...">
                    <flux:select.option value="">— انتخاب کلید —</flux:select.option>
                    @foreach ($this->availableKeys as $key)
                        <flux:select.option value="{{ $key }}">{{ $key }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newFilterOperator">
                    <flux:select.option value="contains">شامل</flux:select.option>
                    <flux:select.option value="equals">برابر با</flux:select.option>
                    <flux:select.option value="gt">بزرگتر از</flux:select.option>
                    <flux:select.option value="gte">بزرگتر یا مساوی</flux:select.option>
                    <flux:select.option value="lt">کوچکتر از</flux:select.option>
                    <flux:select.option value="lte">کوچکتر یا مساوی</flux:select.option>
                    <flux:select.option value="empty">خالی</flux:select.option>
                    <flux:select.option value="not_empty">غیرخالی</flux:select.option>
                </flux:select>

                <flux:input wire:model="newFilterValue" placeholder="مقدار..." />

                <flux:button variant="primary" icon="plus" wire:click="addFilter">افزودن فیلتر</flux:button>
            </div>

            @if (!empty($filters))
                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button size="sm" variant="ghost" wire:click="clearFilters" class="text-red-600">
                        پاک کردن همه فیلترها
                    </flux:button>
                </div>
            @endif
        </flux:card>
    @endif

    {{-- ============ BULK BAR (records) ============ --}}
    @if (!empty($selectedRecords))
        <flux:card class="p-3 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                    {{ count($selectedRecords) }} رکورد انتخاب شده
                </span>

                <flux:spacer />

                @if ($showTrash)
                    <flux:button size="sm" variant="outline" icon="arrow-path" wire:click="bulkRestore">
                        بازیابی
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="danger"
                        icon="trash"
                        wire:click="bulkForceDelete"
                        wire:confirm="آیا مطمئنی؟ این عمل قابل بازگشت نیست."
                    >
                        حذف دائمی
                    </flux:button>
                @else
                    <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="openBulkEdit">
                        ویرایش گروهی
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="outline"
                        icon="arrow-down-tray"
                        wire:click="exportSelected('json')"
                    >
                        خروجی انتخاب‌شده‌ها
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="danger"
                        icon="trash"
                        wire:click="bulkDelete"
                        wire:confirm="آیا مطمئنی؟ رکوردها به سطل آشغال می‌روند."
                    >
                        انتقال به سطل آشغال
                    </flux:button>
                @endif

                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearSelection">
                    لغو انتخاب
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- ============ COLUMN SELECTION BAR ============ --}}
    @if (!empty($selectedExportColumns))
        <flux:card class="p-3 bg-violet-50 dark:bg-violet-900/20 border-violet-200 dark:border-violet-800">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-violet-900 dark:text-violet-100">
                    {{ count($selectedExportColumns) }} ستون برای خروجی:
                </span>

                @foreach ($selectedExportColumns as $col)
                    <code class="text-xs px-2 py-1 bg-white dark:bg-zinc-900 rounded border border-violet-200 dark:border-violet-800">
                        {{ $col }}
                    </code>
                @endforeach

                <flux:spacer />

                <flux:modal.trigger name="field-export-modal">
                    <flux:button size="sm" variant="primary" icon="arrow-down-tray">
                        خروجی این ستون‌ها
                    </flux:button>
                </flux:modal.trigger>

                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="clearExportColumns"
                >
                    لغو
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- ============ CONTENT ============ --}}
    @if ($this->records->isEmpty())
        <flux:card class="text-center py-12">
            <flux:text>
                @if ($showTrash) سطل آشغال خالی است. @else رکوردی یافت نشد. @endif
            </flux:text>
            @unless ($showTrash)
                <div class="mt-4">
                    <flux:button
                        :href="route('projects.records.create', ['current_team' => $team, 'project' => $project])"
                        variant="filled"
                        wire:navigate
                    >
                        اولین رکورد را بساز
                    </flux:button>
                </div>
            @endunless
        </flux:card>
    @else
        @if ($viewMode === 'table')
            @include('pages.projects.records.partials.table')
        @else
            @include('pages.projects.records.partials.tree')
        @endif

        <div class="mt-4">
            {{ $this->records->links() }}
        </div>
    @endif

    {{-- ============ MODALS ============ --}}
    @include('pages.projects.records.partials.column-picker')
    @include('pages.projects.records.partials.bulk-edit-modal')
    @include('pages.projects.records.partials.export-modal')
    @include('pages.projects.records.partials.field-export-modal')
</div>