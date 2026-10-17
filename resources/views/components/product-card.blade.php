@props(['product'])

@php
    $image = $product->primaryImage ?? $product->images->first();
    $stock = $product->variants->sum('stock_quantity');
    $cheapest = $product->variants->where('is_active', true)->min('price') ?? $product->base_price;
@endphp

<article class="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white transition hover:border-stone-300 hover:shadow-md">
    <a href="{{ route('product', $product) }}" class="relative block aspect-[3/4] overflow-hidden bg-stone-100">
        @if ($image)
            <img src="{{ Storage::disk('public')->url($image->path) }}"
                 alt="{{ $image->alt ?? $product->name }}" loading="lazy"
                 class="size-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="flex size-full items-center justify-center text-sm text-stone-400">بلا صورة</span>
        @endif

        @if ($stock <= 0)
            <span class="absolute top-2 start-2 rounded-full bg-stone-900/85 px-2.5 py-1 text-[11px] font-bold text-white">
                نفدت الكمية
            </span>
        @elseif ($product->compare_at_price && (float) $product->compare_at_price > (float) $cheapest)
            <span class="absolute top-2 start-2 rounded-full bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white">
                خصم
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($product->brand)
            <p class="text-[11px] font-medium uppercase tracking-wide text-stone-400">{{ $product->brand->name }}</p>
        @endif

        <h3 class="text-sm font-semibold leading-snug text-stone-900">
            <a href="{{ route('product', $product) }}" class="hover:text-amber-700">{{ $product->name }}</a>
        </h3>

        <div class="mt-auto flex items-center justify-between gap-2">
            <x-price :amount="$cheapest" :compare="$product->compare_at_price" size="sm" />

            @auth
                <form action="{{ route('wishlist.toggle', $product) }}" method="POST" data-ajax data-ajax-wishlist>
                    @csrf
                    <button type="submit" title="أضف إلى المفضّلة"
                            class="rounded-full p-1.5 text-stone-400 transition hover:bg-red-50 hover:text-red-600">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3-2.5 0-4.5 2-4.5 4.5 0 6 8.5 10.5 8.5 10.5S21 14.25 21 8.25Z"/>
                        </svg>
                    </button>
                </form>
            @endauth
        </div>
    </div>
</article>
