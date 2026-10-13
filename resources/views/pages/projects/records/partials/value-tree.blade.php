@foreach ($nodes as $node)
    <div
        class="border-r-2 border-neutral-200 dark:border-neutral-700 pr-4 my-2"
        style="margin-right: {{ $level * 1.5 }}rem"
    >
        <div class="flex items-start gap-2">
            <div class="font-medium text-sm">{{ $node->key }}</div>
            @if ($node->label)
                <div class="text-xs text-neutral-400">{{ $node->label }}</div>
            @endif
            <div class="text-xs text-neutral-400 bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 rounded">
                {{ $node->value_type }}
            </div>
        </div>

        <div class="mt-1">
            @php $value = $node->typedValue(); @endphp

            @if (is_bool($value))
                @if ($value)
                    <flux:badge color="green" size="sm">بله</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">خیر</flux:badge>
                @endif
            @elseif ($value instanceof \DateTimeInterface)
                {{ $value->toDateTimeString() }}
            @elseif (is_null($value) || $value === '')
                <span class="text-neutral-400">—</span>
            @else
                <span class="whitespace-pre-line">{{ $value }}</span>
            @endif
        </div>

        @if ($node->children->isNotEmpty())
            @include('pages.projects.records.partials.value-tree', [
                'nodes' => $node->children,
                'level' => $level + 1,
            ])
        @endif
    </div>
@endforeach