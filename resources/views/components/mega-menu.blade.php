{{--
    Storefront category navigation.

    Desktop: the first roots sit in a bar; a root with children opens a
    full-width panel — one column per second-level child with its main image,
    deeper levels nested inside that column, and a feature tile for the root
    itself. Roots past the seventh move into a "المزيد" panel rather than
    pushing the bar past the viewport, which is what used to give the whole
    page a horizontal scrollbar.
    Mobile: a rail of the roots, with the whole tree one tap away as nested
    <details> accordions.

    The two halves render separately because they live in different places:
    the bar belongs in the sticky header, while on a phone a sticky header
    carrying a rail and an accordion would eat a quarter of the screen — so
    the layout drops the mobile half below the header, where it scrolls away.

    Pure CSS hover/focus-within — the storefront ships no JS.

    @param \Illuminate\Support\Collection $categories root categories, children nested
    @param string $variant  'desktop' or 'mobile'
--}}
@props(['categories', 'variant' => 'desktop'])

@php
    // Seven fits a 1280px bar with the longest Arabic root labels in this
    // catalogue and still leaves room for "كل المنتجات" and "المزيد".
    $primary = $categories->take(7);
    $overflow = $categories->slice(7);
@endphp

@if ($categories->isNotEmpty() && $variant === 'desktop')
    <nav class="hidden border-t border-stone-200/70 md:block" aria-label="التصنيفات">
        <div class="relative mx-auto max-w-7xl px-4">
            <ul class="flex items-stretch gap-0.5 text-sm">
                <li>
                    <a href="{{ route('catalog') }}"
                       class="flex items-center gap-1.5 px-3 py-3 font-bold text-stone-900 transition hover:text-brand-700">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                        كل المنتجات
                    </a>
                </li>

                @foreach ($primary as $root)
                    {{-- `static` so the panel below anchors to the full-width
                         container rather than to this item. --}}
                    <li class="group static">
                        <a href="{{ route('category', $root) }}"
                           class="relative flex items-center gap-2 px-3 py-3 font-medium text-stone-600 transition
                                  after:absolute after:inset-x-3 after:bottom-1.5 after:h-0.5 after:origin-center
                                  after:scale-x-0 after:rounded-full after:bg-brand-500 after:transition
                                  hover:text-brand-700 group-hover:text-brand-700 group-hover:after:scale-x-100">
                            <x-category-thumb :category="$root" size="size-6" rounded="rounded-full" />
                            {{ $root->name }}
                            @if ($root->children->isNotEmpty())
                                <svg class="size-3 text-stone-400 transition group-hover:rotate-180"
                                     fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"
                                     aria-hidden="true">
                                    <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                </svg>
                            @endif
                        </a>

                        @if ($root->children->isNotEmpty())
                            <div class="invisible absolute inset-x-0 top-full z-40 translate-y-1 px-4 opacity-0
                                        transition duration-150
                                        group-hover:visible group-hover:translate-y-0 group-hover:opacity-100
                                        group-focus-within:visible group-focus-within:translate-y-0
                                        group-focus-within:opacity-100">
                                <div class="grid gap-8 rounded-b-3xl border border-t-0 border-stone-200/80 bg-white
                                            p-7 shadow-lift lg:grid-cols-[1fr_280px]">
                                    <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($root->children as $child)
                                            <div class="min-w-0">
                                                <a href="{{ route('category', $child) }}"
                                                   class="group/item flex items-center gap-3 rounded-xl p-1.5 transition hover:bg-brand-50">
                                                    <x-category-thumb :category="$child" size="size-11" rounded="rounded-xl" />
                                                    <span class="truncate text-sm font-bold text-stone-900
                                                                 group-hover/item:text-brand-800">
                                                        {{ $child->name }}
                                                    </span>
                                                </a>

                                                @if ($child->children->isNotEmpty())
                                                    <x-mega-menu-branch :categories="$child->children" />
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- feature tile: the root's own image --}}
                                    <a href="{{ route('category', $root) }}"
                                       class="group/tile relative hidden overflow-hidden rounded-2xl bg-stone-100 lg:block">
                                        @if ($root->image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($root->image) }}"
                                                 alt="" aria-hidden="true" loading="lazy"
                                                 class="size-full object-cover transition duration-500 group-hover/tile:scale-105">
                                        @endif
                                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-stone-950/90
                                                     to-transparent p-5 text-sm font-bold text-white">
                                            تصفّح كل {{ $root->name }}
                                            <span aria-hidden="true">&larr;</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach

                @if ($overflow->isNotEmpty())
                    <li class="group static">
                        <button type="button"
                                class="flex items-center gap-1.5 px-3 py-3 font-medium text-stone-600 transition
                                       hover:text-brand-700 group-hover:text-brand-700">
                            المزيد
                            <svg class="size-3 text-stone-400 transition group-hover:rotate-180"
                                 fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        <div class="invisible absolute inset-x-0 top-full z-40 translate-y-1 px-4 opacity-0
                                    transition duration-150
                                    group-hover:visible group-hover:translate-y-0 group-hover:opacity-100
                                    group-focus-within:visible group-focus-within:translate-y-0
                                    group-focus-within:opacity-100">
                            <div class="grid gap-x-8 gap-y-2 rounded-b-3xl border border-t-0 border-stone-200/80
                                        bg-white p-7 shadow-lift sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($overflow as $root)
                                    <a href="{{ route('category', $root) }}"
                                       class="group/item flex items-center gap-3 rounded-xl p-1.5 transition hover:bg-brand-50">
                                        <x-category-thumb :category="$root" size="size-10" rounded="rounded-xl" />
                                        <span class="truncate text-sm font-semibold text-stone-800 group-hover/item:text-brand-800">
                                            {{ $root->name }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@elseif ($categories->isNotEmpty())
    <nav class="border-b border-stone-200/70 bg-white md:hidden" aria-label="التصنيفات">
        {{-- A thumb-height rail: 44px tall pills that scroll under the finger
             and snap, rather than a wrapped block of small tap targets. --}}
        <div class="snap-row no-scrollbar gap-2 px-4 py-3">
            <a href="{{ route('catalog') }}"
               class="snap-row-item flex items-center rounded-full bg-stone-900 px-5 py-2.5 text-sm font-bold text-white">
                كل المنتجات
            </a>
            @foreach ($categories as $root)
                <a href="{{ route('category', $root) }}"
                   class="snap-row-item flex items-center gap-2 rounded-full border border-stone-200 bg-white
                          py-1.5 pe-4 ps-1.5 text-sm font-semibold text-stone-700">
                    <x-category-thumb :category="$root" size="size-8" rounded="rounded-full" />
                    {{ $root->name }}
                </a>
            @endforeach
        </div>

        <details class="group border-t border-stone-200/70">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3.5 text-sm font-bold text-stone-800">
                تصفّح كل التصنيفات
                <svg class="size-4 text-stone-400 transition group-open:rotate-180" fill="none" stroke="currentColor"
                     stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>

            <div class="border-t border-stone-100 px-4 pb-4 pt-2">
                <x-mega-menu-mobile-branch :categories="$categories" />
            </div>
        </details>
    </nav>
@endif
