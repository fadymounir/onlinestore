@extends($shopLayout ?? 'layouts.shop')

@section('title', 'المفضّلة')

@section('content')
    <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">المفضّلة</h1>

    @if ($products->isEmpty() && $hidden > 0)
        {{--
            Saved products, none of them available: out of stock, or taken off
            the shop since. Saying "لا منتجات في المفضّلة" here would be a
            plain untruth — the customer saved these and nothing was removed.
        --}}
        {{-- No count in the sentence: Arabic agreement changes with it, and a
             number in the wrong form reads worse than no number at all. --}}
        <x-empty title="لا شيء من مفضّلتك متاح حالياً"
                 description="ما حفظته ما زال محفوظاً — نفدت الكمية منه الآن، وسيظهر هنا من جديد فور توفّره."
                 :action="route('catalog')" action-label="تصفّح المتاح الآن" />
    @elseif ($products->isEmpty())
        <x-empty title="لا منتجات في المفضّلة"
                 description="اضغط على القلب في أي منتج لحفظه هنا."
                 :action="route('catalog')" />
    @else
        {{--
            data-ajax-refresh: the hearts on these cards post in the background
            like everywhere else, but here the action removes the card it was
            clicked on. The marker tells app.js to re-render the page once the
            server has confirmed it, so the product leaves the list — and the
            list falls back to the empty state when the last one goes.
        --}}
        <div data-ajax-refresh>
            {{-- Same two ids as the catalogue, for the same two reasons: cards
                 are appended to the grid as the shopper scrolls, and the link
                 that asked for them is replaced with the next one. --}}
            <div id="wishlist-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4">
                @include('shop.partials.product-cards')
            </div>

            <div id="wishlist-more">
                @include('shop.partials.load-more', ['grid' => '#wishlist-grid'])
            </div>
        </div>
    @endif
@endsection
