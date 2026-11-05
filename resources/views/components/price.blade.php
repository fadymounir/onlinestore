@props(['amount', 'compare' => null, 'size' => 'base'])

@php
    $classes = match ($size) {
        'lg' => 'text-3xl font-extrabold tracking-tight',
        'sm' => 'text-[15px] font-bold',
        default => 'text-lg font-bold',
    };
@endphp

{{-- tabular-nums: prices sit in columns on the cart and the grid, and
     proportional digits make those columns waver. --}}
<span class="inline-flex flex-wrap items-baseline gap-2 tabular-nums">
    <span class="{{ $classes }} text-stone-900">
        {{ number_format((float) $amount, 2) }}
        <span class="text-xs font-medium text-stone-400">ج.م</span>
    </span>

    @if ($compare && (float) $compare > (float) $amount)
        <span class="text-sm text-stone-400 line-through">{{ number_format((float) $compare, 2) }}</span>
        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-700">
            وفّر {{ round((1 - (float) $amount / (float) $compare) * 100) }}%
        </span>
    @endif
</span>
