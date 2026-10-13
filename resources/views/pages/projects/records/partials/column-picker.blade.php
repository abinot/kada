<flux:modal name="column-picker" class="max-w-2xl">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">انتخاب ستون‌های نمایش</flux:heading>
            <flux:subheading>
                {{ count($availableKeys) }} کلید در این پروژه موجود است. {{ count($selectedColumns) }} ستون انتخاب شده.
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:input
                wire:model.live.debounce.300ms="columnSearch"
                placeholder="جستجوی کلید..."
                icon="magnifying-glass"
                class="flex-1"
            />
            <flux:button size="sm" wire:click="selectTopColumns(10)" variant="outline">
                ۱۰ پرتکرار
            </flux:button>
            <flux:button size="sm" wire:click="selectTopColumns(20)" variant="outline">
                ۲۰ پرتکرار
            </flux:button>
            <flux:button size="sm" wire:click="clearColumns" variant="ghost" class="text-red-600">
                پاک کردن
            </flux:button>
        </div>

        @php
            $freq = $project->keyFrequency(500);
        @endphp

        <div class="max-h-96 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-md p-2 space-y-1">
            @forelse ($this->filteredAvailableKeys as $key)
                <label class="flex items-center gap-2 p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer">
                    <input
                        type="checkbox"
                        wire:click="toggleColumn('{{ $key }}')"
                        @checked(in_array($key, $selectedColumns))
                        class="rounded border-zinc-300 dark:border-zinc-600"
                    />
                    <code class="text-xs flex-1">{{ $key }}</code>
                    <span class="text-xs text-neutral-400">{{ $freq[$key] ?? 0 }} بار</span>
                </label>
            @empty
                <flux:text class="text-center py-4 text-neutral-500">کلیدی یافت نشد.</flux:text>
            @endforelse
        </div>

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="filled">بستن</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>