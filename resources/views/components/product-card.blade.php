{{--
    A product in a grid.

    The image area carries everything that is true before a click: the second
    photograph on hover (the single highest-converting pattern on a fashion
    grid — it answers "what does the back look like" without a page load), the
    discount or sold-out badge, and the wishlist heart.

    @param \App\Models\Product $product
--}}
@props(['product'])

@php
    $image = $product->primaryImage ?? $product->images->first();

    // Only when the gallery is already loaded — a card in a grid must not go
    // back to the database for a hover state. See the listing queries in
    // App\Http\Controllers\Shop.
    $hoverImage = $product->relationLoaded('images')
        ? $product->images->first(fn ($candidate) => $candidate->id !== $image?->id)
        : null;

    $stock = $product->variants->sum('stock_quantity');
    $cheapest = $product->variants->where('is_active', true)->min('price') ?? $product->base_price;
    $hasDiscount = $product->compare_at_price && (float) $product->compare_at_price > (float) $cheapest;
    $off = $hasDiscount ? round((1 - (float) $cheapest / (float) $product->compare_at_price) * 100) : 0;
@endphp

<article class="group relative flex flex-col overflow-hidden rounded-3xl bg-white shadow-soft transition
                duration-300 hover:-translate-y-1 hover:shadow-lift">
    <a href="{{ route('product', $product) }}" class="relative block aspect-[3/4] overflow-hidden bg-stone-100">
        @if ($image)
            <img src="{{ Storage::disk('public')->url($image->path) }}"
                 alt="{{ $image->alt ?? $product->name }}" loading="lazy"
                 class="size-full object-cover transition duration-500 group-hover:scale-105
                        {{ $hoverImage ? 'group-hover:opacity-0' : '' }}">

            @if ($hoverImage)
                <img src="{{ Storage::disk('public')->url($hoverImage->path) }}"
                     alt="" aria-hidden="true" loading="lazy"
                     class="absolute inset-0 size-full scale-105 object-cover opacity-0 transition duration-500
                            group-hover:scale-100 group-hover:opacity-100">
            @endif
        @else
            <span class="flex size-full items-center justify-center text-sm text-stone-400">بلا صورة</span>
        @endif

        {{-- A thin wash at the foot of the photo so a white-on-white product
             shot still separates from the card below it. --}}
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-stone-900/10 to-transparent"></span>

        @if ($stock <= 0)
            <span class="absolute top-2.5 start-2.5 rounded-full bg-stone-900/90 px-2.5 py-1 text-[11px] font-bold text-white backdrop-blur">
                نفدت الكمية
            </span>
        @elseif ($hasDiscount)
            <span class="absolute top-2.5 start-2.5 rounded-full bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-soft">
                خصم {{ $off }}%
            </span>
        @endif
    </a>

    @auth
        {{-- Outside the anchor above, so the heart is not a link inside a link. --}}
        <form action="{{ route('wishlist.toggle', $product) }}" method="POST" data-ajax data-ajax-wishlist
              class="absolute top-2.5 end-2.5">
            @csrf
            <button type="submit" title="أضف إلى المفضّلة" aria-label="أضف إلى المفضّلة"
                    class="flex size-10 items-center justify-center rounded-full bg-white/90 text-stone-500
                           shadow-soft backdrop-blur transition hover:text-red-600">
                <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3-2.5 0-4.5 2-4.5 4.5 0 6 8.5 10.5 8.5 10.5S21 14.25 21 8.25Z"/>
                </svg>
            </button>
        </form>
    @endauth

    <div class="flex flex-1 flex-col gap-1 p-3 sm:gap-1.5 sm:p-4">
        @if ($product->brand)
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-700">{{ $product->brand->name }}</p>
        @endif

        <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-stone-900">
            <a href="{{ route('product', $product) }}" class="transition hover:text-brand-700">{{ $product->name }}</a>
        </h3>

        <div class="mt-auto pt-2">
            <x-price :amount="$cheapest" :compare="$product->compare_at_price" size="sm" />
        </div>
    </div>
</article>
