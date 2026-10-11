@props(['amount', 'compare' => null, 'size' => 'base'])

@php
    $classes = match ($size) {
        'lg' => 'text-2xl font-bold',
        'sm' => 'text-sm font-semibold',
        default => 'text-base font-semibold',
    };
@endphp

<span class="inline-flex flex-wrap items-baseline gap-2">
    <span class="{{ $classes }} text-stone-900">
        {{ number_format((float) $amount, 2) }}
        <span class="text-xs font-normal text-stone-500">ج.م</span>
    </span>

    @if ($compare && (float) $compare > (float) $amount)
        <span class="text-sm text-stone-400 line-through">{{ number_format((float) $compare, 2) }}</span>
        <span class="rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-bold text-red-700">
            وفّر {{ round((1 - (float) $amount / (float) $compare) * 100) }}%
        </span>
    @endif
</span>
