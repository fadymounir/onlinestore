{{--
    Third level and deeper inside a mega-menu column. Recurses into itself, so
    the menu follows the tree however deep the dashboard nests it.

    @param \Illuminate\Support\Collection $categories
--}}
@props(['categories'])

<ul class="mt-2.5 space-y-1 border-s border-stone-100 ps-3">
    @foreach ($categories as $category)
        <li>
            <a href="{{ route('category', $category) }}"
               class="group/leaf flex items-center gap-2 py-0.5">
                <x-category-thumb :category="$category" size="size-6" rounded="rounded-md" />
                <span class="truncate text-[13px] text-stone-600 group-hover/leaf:text-amber-700">
                    {{ $category->name }}
                </span>
            </a>

            @if ($category->children->isNotEmpty())
                <x-mega-menu-branch :categories="$category->children" />
            @endif
        </li>
    @endforeach
</ul>
