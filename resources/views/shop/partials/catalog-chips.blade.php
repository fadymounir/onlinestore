{{--
    The filters currently in force, as removable chips.

    Each chip's link is the same URL minus that one parameter, so a filter can
    be dropped without opening the sidebar and hunting for the control that set
    it. Nothing here reads the sidebar — the URL is the single statement of
    what is being filtered, which is why this renders identically whether the
    page was loaded whole or the grid was swapped underneath it.

    Its own partial because a filter change swaps the grid without re-rendering
    the page, and this row has to move with it — see CatalogController::respond.

    @param \Illuminate\Support\Collection $brands
    @param \App\Models\Category|null $category
--}}
@php
    $chips = [];

    if (request('q')) {
        $chips[] = ['label' => '«'.request('q').'»', 'url' => request()->fullUrlWithoutQuery(['q', 'page'])];
    }

    if (request('min') || request('max')) {
        $chips[] = [
            'label' => 'السعر '.(request('min') ?: '0').' – '.(request('max') ?: '∞').' ج.م',
            'url' => request()->fullUrlWithoutQuery(['min', 'max', 'page']),
        ];
    }

    foreach ((array) request('brand', []) as $id) {
        if ($brand = $brands->firstWhere('id', (int) $id)) {
            $chips[] = [
                'label' => $brand->name,
                'url' => request()->fullUrlWithQuery([
                    'brand' => array_values(array_diff((array) request('brand', []), [$id])) ?: null,
                    'page' => null,
                ]),
            ];
        }
    }
@endphp

@if (filled($chips))
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-stone-500">الفلاتر:</span>
        @foreach ($chips as $chip)
            <a href="{{ $chip['url'] }}"
               class="group inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 text-xs
                      font-semibold text-brand-800 transition hover:bg-brand-100">
                {{ $chip['label'] }}
                <svg class="size-3.5 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </a>
        @endforeach
        <a href="{{ $category ? route('category', $category) : route('catalog') }}"
           class="text-xs font-semibold text-stone-500 underline underline-offset-4 transition hover:text-stone-900">
            مسح الكل
        </a>
    </div>
@endif
