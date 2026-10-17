{{--
    The product grid and its pager.

    Split out of catalog.blade.php so CatalogController can return just this
    much to an AJAX filter, sort or page change. The count line above the grid
    moves too, so it carries a data-ajax-replace marker and is swapped by id
    rather than being part of this fragment.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $products
--}}
@if ($products->isEmpty())
    <x-empty title="لا توجد منتجات مطابقة"
             description="جرّب توسيع نطاق السعر أو إزالة بعض الفلاتر."
             :action="route('catalog')" action-label="تصفّح كل المنتجات" />
@else
    <div class="grid grid-cols-2 gap-5 sm:grid-cols-3">
        @foreach ($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>

    {{-- The pager's links are plain <a href>; the JS layer intercepts any
         anchor inside an ajax region and swaps instead of navigating. --}}
    <div class="mt-10">{{ $products->links() }}</div>
@endif
