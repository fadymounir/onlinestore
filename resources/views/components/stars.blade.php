@props(['rating' => 0, 'count' => null])

<span class="inline-flex items-center gap-1 align-middle">
    <span class="text-amber-500" aria-hidden="true">{!! str_repeat('★', (int) round($rating)) !!}{!! str_repeat('☆', 5 - (int) round($rating)) !!}</span>
    <span class="sr-only">{{ number_format((float) $rating, 1) }} من 5</span>
    @if ($count !== null)
        <span class="text-xs text-stone-500">({{ $count }})</span>
    @endif
</span>
