<flux:modal name="field-export-modal" class="max-w-lg">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">خروجی ستون‌های انتخاب‌شده</flux:heading>
            <flux:subheading>
                {{ count($selectedExportColumns) }} ستون
                @if (!empty($selectedRecords))
                    — {{ count($selectedRecords) }} رکورد انتخاب شده
                @else
                    — همه‌ی رکوردهای پروژه
                @endif
            </flux:subheading>
        </div>

        <div class="p-3 bg-zinc-50 dark:bg-zinc-800 rounded-md">
            <div class="flex flex-wrap gap-1">
                @foreach ($selectedExportColumns as $col)
                    <code class="text-xs px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded">{{ $col }}</code>
                @endforeach
            </div>
        </div>

        <div class="space-y-2">
            <button
                type="button"
                wire:click="exportSelected('json')"
                class="w-full flex items-center gap-3 p-3 rounded-md border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-start"
            >
                <flux:icon name="code-bracket" class="size-5 text-blue-600" />
                <div class="flex-1">
                    <div class="font-medium">JSON</div>
                    <div class="text-xs text-neutral-500">فقط ستون‌های انتخاب‌شده</div>
                </div>
            </button>

            <button
                type="button"
                wire:click="exportSelected('ndjson')"
                class="w-full flex items-center gap-3 p-3 rounded-md border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-start"
            >
                <flux:icon name="queue-list" class="size-5 text-emerald-600" />
                <div class="flex-1">
                    <div class="font-medium">NDJSON</div>
                    <div class="text-xs text-neutral-500">هر خط یک رکورد</div>
                </div>
            </button>

            <button
                type="button"
                wire:click="exportSelected('sqlite')"
                class="w-full flex items-center gap-3 p-3 rounded-md border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-start"
            >
                <flux:icon name="circle-stack" class="size-5 text-violet-600" />
                <div class="flex-1">
                    <div class="font-medium">SQLite</div>
                    <div class="text-xs text-neutral-500">دیتابیس مستقل</div>
                </div>
            </button>
        </div>

        <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm" class="mb-2">یا خروجی کل میزکار (تیم)</flux:heading>
            <flux:text class="text-xs text-neutral-500 mb-3">
                همه‌ی پروژه‌ها و رکوردهای تیم {{ $team->name }} در یک فایل.
            </flux:text>
            <div class="grid grid-cols-3 gap-2">
                <flux:button size="sm" variant="outline" wire:click="exportWorkspace('json')">
                    JSON
                </flux:button>
                <flux:button size="sm" variant="outline" wire:click="exportWorkspace('ndjson')">
                    NDJSON
                </flux:button>
                <flux:button size="sm" variant="outline" wire:click="exportWorkspace('sqlite')">
                    SQLite
                </flux:button>
            </div>
        </div>

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="filled">بستن</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>