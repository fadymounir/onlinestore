{{--
    Mobile category accordion. A node with children becomes a <details> whose
    summary opens the branch; the "تصفّح الكل" link inside goes to the category
    page itself. Recurses to any depth.

    @param \Illuminate\Support\Collection $categories
--}}
@props(['categories'])

<ul class="space-y-1">
    @foreach ($categories as $category)
        <li>
            @if ($category->children->isEmpty())
                <a href="{{ route('category', $category) }}"
                   class="flex items-center gap-2.5 py-2 text-sm text-stone-700 hover:text-amber-700">
                    <x-category-thumb :category="$category" size="size-8" />
                    {{ $category->name }}
                </a>
            @else
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-2.5 py-2 text-sm text-stone-700">
                        <x-category-thumb :category="$category" size="size-8" />
                        <span class="truncate">{{ $category->name }}</span>
                        <svg class="ms-auto size-4 shrink-0 text-stone-400 transition group-open:rotate-180"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                             aria-hidden="true">
                            <path stroke-linecap="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </summary>

                    <div class="border-s border-stone-100 ps-3">
                        <a href="{{ route('category', $category) }}"
                           class="block py-2 text-xs font-semibold text-amber-700 hover:text-amber-800">
                            تصفّح كل {{ $category->name }}
                        </a>

                        <x-mega-menu-mobile-branch :categories="$category->children" />
                    </div>
                </details>
            @endif
        </li>
    @endforeach
</ul>
