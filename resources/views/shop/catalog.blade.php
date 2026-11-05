@extends($shopLayout ?? 'layouts.shop')

@section('title', $heading)

@section('content')
    <nav class="mb-5 flex flex-wrap items-center gap-1.5 text-sm text-stone-500" aria-label="مسار التصفّح">
        <a href="{{ route('home') }}" class="transition hover:text-brand-700">الرئيسية</a>
        @foreach ($ancestors as $ancestor)
            <span aria-hidden="true" class="text-stone-300">/</span>
            <a href="{{ route('category', $ancestor) }}" class="transition hover:text-brand-700">{{ $ancestor->name }}</a>
        @endforeach
        <span aria-hidden="true" class="text-stone-300">/</span>
        <span class="font-semibold text-stone-900">{{ $heading }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">{{ $heading }}</h1>
            <p id="catalog-count" class="mt-1.5 text-sm text-stone-500">{{ $products->total() }} منتج</p>
        </div>

        {{-- data-ajax-nav: swaps the grid and rewrites the URL, no page reload --}}
        <form action="{{ url()->current() }}" method="GET" data-ajax-nav
              class="flex items-center gap-2 rounded-full bg-white p-1.5 ps-4 shadow-soft">
            @foreach (request()->except(['sort', 'page']) as $key => $value)
                @foreach (is_array($value) ? $value : [$value] as $v)
                    <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                @endforeach
            @endforeach
            <label for="sort" class="text-sm text-stone-500">ترتيب</label>
            {{-- data-ajax-auto replaces the old onchange="this.form.submit()" --}}
            <select name="sort" id="sort" data-ajax-auto
                    class="rounded-full bg-stone-100 px-4 py-2 text-sm font-semibold text-stone-800 outline-none
                           transition focus:ring-2 focus:ring-brand-500/30">
                <option value="newest" @selected(request('sort') === 'newest')>الأحدث</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>الأقل سعراً</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>الأعلى سعراً</option>
            </select>
            <noscript>
                <button type="submit" class="rounded-full bg-stone-900 px-4 py-2 text-sm text-white">ترتيب</button>
            </noscript>
        </form>
    </div>

    {{-- direct subcategories, so a branch page can be drilled into --}}
    @if ($category && $category->children->isNotEmpty())
        <div class="snap-row no-scrollbar mb-6 -mx-4 gap-2 px-4 sm:mx-0 sm:flex-wrap sm:px-0">
            @foreach ($category->children->where('is_active', true) as $child)
                {{-- A real navigation: the heading, breadcrumb and this very list
                     all change with the category, which is a new page, not a filter. --}}
                <a href="{{ route('category', $child) }}"
                   class="snap-row-item flex items-center gap-2 rounded-full bg-white py-1.5 pe-4 ps-1.5 text-sm
                          font-semibold text-stone-700 shadow-soft transition hover:text-brand-800">
                    <x-category-thumb :category="$child" size="size-7" rounded="rounded-full" />
                    {{ $child->name }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- The swap target for the chips: present even when empty, because a
         filter being cleared is as much an update as one being added. --}}
    <div id="catalog-chips">
        @include('shop.partials.catalog-chips')
    </div>

    <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
        {{--
            A checkbox rather than <details>: the panel has to be collapsed on
            a phone and permanently open from `lg` up, and a closed <details>
            cannot be forced open with CSS alone. The input is the `peer` and
            the label is the button, so the whole thing works with no
            JavaScript — and the opening is CSS too, for the same reason.
        --}}
        <aside class="lg:sticky lg:top-36">
            <input type="checkbox" id="filters-toggle" class="peer sr-only">

            <label for="filters-toggle"
                   class="filters-toggle mb-3 flex cursor-pointer items-center justify-between rounded-2xl bg-white
                          px-5 py-4 text-sm font-bold text-stone-900 shadow-soft transition
                          active:scale-[0.99] lg:hidden">
                <span class="flex items-center gap-2">
                    <svg class="size-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" d="M12 3c2.76 0 5.26.3 7.2.78.6.15 1.05.68 1.05 1.3v.9c0 .4-.16.78-.44 1.06l-5.12 5.12a1.5 1.5 0 0 0-.44 1.06v3.1c0 .5-.24.98-.66 1.26l-2.1 1.4a1.5 1.5 0 0 1-2.34-1.25v-4.5c0-.4-.16-.79-.44-1.07L3.6 7.05a1.5 1.5 0 0 1-.44-1.06v-.9c0-.63.44-1.16 1.05-1.3A31 31 0 0 1 12 3Z"/>
                    </svg>
                    تصفية النتائج
                </span>
                <span class="flex items-center gap-2 text-xs font-semibold text-stone-500">
                    {{ $products->total() }} منتج
                    {{-- Turns over as the panel opens, so the tap has an answer
                         before the panel has finished arriving. --}}
                    <svg class="filters-chevron size-4 text-stone-400" fill="none" stroke="currentColor"
                         stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </span>
            </label>

            {{--
                The panel opens by growing its own row from 0fr to 1fr rather
                than by flipping `display`, which has nothing to animate
                between. The card's white and its rounding sit out here on the
                track so the sheet underneath can clip without taking the
                shadow with it; see .filters-panel in resources/css/app.css for
                why the clipping is there at all.
            --}}
            <div class="filters-panel rounded-3xl bg-white shadow-soft lg:shadow-soft">
                <div class="filters-panel__sheet">
                    <form action="{{ url()->current() }}" method="GET" data-ajax-nav
                          class="space-y-6 p-5">
                        @if (request('q'))
                            <input type="hidden" name="q" value="{{ request('q') }}">
                        @endif
                        @if (request('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif

                        <div>
                            <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-stone-500">السعر (ج.م)</h2>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min" min="0" value="{{ request('min') }}" placeholder="من"
                                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm
                                              outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                                <span class="text-stone-300">—</span>
                                <input type="number" name="max" min="0" value="{{ request('max') }}" placeholder="إلى"
                                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm
                                              outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                            </div>
                        </div>

                        @if ($brands->isNotEmpty())
                            <div>
                                <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-stone-500">الماركة</h2>
                                <div class="max-h-60 space-y-1 overflow-y-auto pe-1">
                                    @foreach ($brands as $brand)
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2 py-1.5
                                                      text-sm text-stone-700 transition hover:bg-stone-50">
                                            <input type="checkbox" name="brand[]" value="{{ $brand->id }}" data-ajax-auto
                                                   @checked(in_array((string) $brand->id, (array) request('brand', []), true))
                                                   class="size-4 accent-brand-600">
                                            {{ $brand->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <button type="submit"
                                    class="flex-1 rounded-xl bg-stone-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-stone-800">
                                تطبيق
                            </button>
                            <a href="{{ $category ? route('category', $category) : route('catalog') }}"
                               class="rounded-xl border border-stone-200 px-4 py-3 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                                مسح
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </aside>

        {{-- The swap target for a filter or a sort. Inside it the grid grows
             by itself as the shopper scrolls — see shop/partials/load-more. --}}
        <div id="catalog-results">
            @include('shop.partials.catalog-results')
        </div>
    </div>
@endsection
