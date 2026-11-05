{{--
    The product grid and whatever comes after it.

    Split out of catalog.blade.php so CatalogController can return just this
    much to an AJAX filter or sort. The count line above the grid moves too,
    but it sits outside this fragment and is swapped by id.

    Two ids, because the two things that happen here are different shapes:

      #catalog-grid   cards are *appended* to it as the shopper scrolls
      #catalog-more   the link to the next page, *replaced* with the one after

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $products
--}}
@if ($products->isEmpty())
    <x-empty title="لا توجد منتجات مطابقة"
             description="جرّب توسيع نطاق السعر أو إزالة بعض الفلاتر."
             :action="route('catalog')" action-label="تصفّح كل المنتجات" />
@else
    <div id="catalog-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5">
        @include('shop.partials.product-cards')
    </div>

    <div id="catalog-more">
        @include('shop.partials.load-more', ['grid' => '#catalog-grid'])
    </div>
@endif
