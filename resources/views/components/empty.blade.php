@props(['title', 'description' => null, 'action' => null, 'actionLabel' => null])

<div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
    <p class="text-lg font-semibold text-stone-900">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-stone-600">{{ $description }}</p>
    @endif
    @if ($action)
        <a href="{{ $action }}"
           class="mt-6 inline-block rounded-full bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
            {{ $actionLabel ?? 'تصفّح المنتجات' }}
        </a>
    @endif
</div>
