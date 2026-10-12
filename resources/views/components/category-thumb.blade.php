{{--
    A category's main image, with a lettered placeholder when it has none.

    Pass `size` as a literal Tailwind size utility (e.g. "size-10") so Tailwind
    can see the class in the source and generate it.

    @param \App\Models\Category $category
    @param string $size
    @param string $rounded
--}}
@props(['category', 'size' => 'size-10', 'rounded' => 'rounded-lg'])

<span class="{{ $size }} {{ $rounded }} shrink-0 overflow-hidden bg-stone-100 ring-1 ring-stone-200/80">
    @if ($category->image)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image) }}"
             alt="" aria-hidden="true" loading="lazy"
             class="size-full object-cover">
    @else
        <span class="flex size-full items-center justify-center text-[11px] font-bold text-stone-400">
            {{ mb_substr($category->name, 0, 1) }}
        </span>
    @endif
</span>
