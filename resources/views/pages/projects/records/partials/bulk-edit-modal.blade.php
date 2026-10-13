<flux:modal name="bulk-edit-modal" wire:model="showBulkEditModal" class="max-w-2xl">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">ویرایش گروهی</flux:heading>
            <flux:subheading>
                {{ count($selectedRecords) }} رکورد انتخاب شده. مقادیری که وارد کنی روی همه اعمال می‌شود.
            </flux:subheading>
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @foreach ($bulkEditRows as $i => $row)
                <div class="grid grid-cols-12 gap-2 items-start" wire:key="bulk-{{ $i }}">
                    <div class="col-span-4">
                        <flux:input
                            wire:model="bulkEditRows.{{ $i }}.key"
                            placeholder="کلید (name, phone)"
                            list="bulk-keys-list"
                        />
                    </div>
                    <div class="col-span-3">
                        <flux:select wire:model.live="bulkEditRows.{{ $i }}.type">
                            <flux:select.option value="string">متن</flux:select.option>
                            <flux:select.option value="integer">عدد</flux:select.option>
                            <flux:select.option value="decimal">اعشاری</flux:select.option>
                            <flux:select.option value="boolean">بله/خیر</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="col-span-4">
                        @if ($row['type'] === 'boolean')
                            <flux:switch wire:model="bulkEditRows.{{ $i }}.value" />
                        @else
                            <flux:input
                                wire:model="bulkEditRows.{{ $i }}.value"
                                placeholder="مقدار"
                            />
                        @endif
                    </div>
                    <div class="col-span-1">
                        <flux:button
                            type="button"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removeBulkEditRow({{ $i }})"
                            class="text-red-600"
                        />
                    </div>
                </div>
            @endforeach

            <flux:button type="button" variant="outline" icon="plus" wire:click="addBulkEditRow" size="sm">
                افزودن مقدار
            </flux:button>
        </div>

        <datalist id="bulk-keys-list">
            @foreach ($this->availableKeys as $key)
                <option value="{{ $key }}"></option>
            @endforeach
        </datalist>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">انصراف</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="applyBulkEdit">
                اعمال روی {{ count($selectedRecords) }} رکورد
            </flux:button>
        </div>
    </div>
</flux:modal>