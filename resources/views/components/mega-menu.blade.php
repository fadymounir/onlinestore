{{--
    Storefront category navigation.

    Desktop: root categories in a bar; a root with children opens a full-width
    panel — one column per second-level child with its main image, deeper levels
    nested inside that column, and a feature tile for the root itself.
    Mobile: the same tree as nested <details> accordions.

    Pure CSS hover/focus-within — the storefront ships no JS.

    @param \Illuminate\Support\Collection $categories root categories, children nested
--}}
@props(['categories'])

@if ($categories->isNotEmpty())
    <nav class="border-t border-stone-100 bg-white" aria-label="التصنيفات">
        {{-- desktop --}}
        <div class="relative mx-auto hidden max-w-7xl px-4 md:block">
            <ul class="flex items-stretch gap-1 whitespace-nowrap text-sm">
                <li>
                    <a href="{{ route('catalog') }}"
                       class="block px-3 py-3 font-medium text-stone-700 hover:text-amber-700">
                        كل المنتجات
                    </a>
                </li>

                @foreach ($categories as $root)
                    {{-- `static` so the panel below anchors to the full-width
                         container rather than to this item. --}}
                    <li class="group static">
                        <a href="{{ route('category', $root) }}"
                           class="flex items-center gap-2 px-3 py-3 font-medium text-stone-700
                                  hover:text-amber-700 group-hover:text-amber-700">
                            <x-category-thumb :category="$root" size="size-6" rounded="rounded-md" />
                            {{ $root->name }}
                            @if ($root->children->isNotEmpty())
                                <svg class="size-3.5 text-stone-400 transition group-hover:rotate-180"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
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
                                <div class="grid gap-8 rounded-b-2xl border border-t-0 border-stone-200 bg-white
                                            p-6 shadow-xl lg:grid-cols-[1fr_260px]">
                                    <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($root->children as $child)
                                            <div class="min-w-0">
                                                <a href="{{ route('category', $child) }}"
                                                   class="group/item flex items-center gap-2.5">
                                                    <x-category-thumb :category="$child" size="size-11" />
                                                    <span class="truncate text-sm font-bold text-stone-900
                                                                 group-hover/item:text-amber-700">
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
                                       class="relative hidden overflow-hidden rounded-xl bg-stone-100 lg:block">
                                        @if ($root->image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($root->image) }}"
                                                 alt="" aria-hidden="true" loading="lazy"
                                                 class="size-full object-cover transition duration-300 hover:scale-105">
                                        @endif
                                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-stone-900/85
                                                     to-transparent p-4 text-sm font-bold text-white">
                                            تصفّح كل {{ $root->name }}
                                            <span aria-hidden="true">&larr;</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- mobile --}}
        <details class="md:hidden">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold text-stone-800">
                تصفّح التصنيفات
                <svg class="size-4 text-stone-400" fill="none" stroke="currentColor" stroke-width="2.5"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>

            <div class="border-t border-stone-100 px-4 pb-4 pt-2">
                <a href="{{ route('catalog') }}"
                   class="block py-2 text-sm font-medium text-stone-700 hover:text-amber-700">
                    كل المنتجات
                </a>

                <x-mega-menu-mobile-branch :categories="$categories" />
            </div>
        </details>
    </nav>
@endif
