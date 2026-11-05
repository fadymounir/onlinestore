{{--
    A run of product cards and nothing else — no grid, no wrapper.

    Its own partial because it is what the server sends when a listing loads
    its next page: the cards are appended to a grid that is already on screen,
    so anything around them would be a second copy of it. The grid itself, with
    whatever column count that page wants, stays in the view that owns it.

    @param \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\Paginator $products
--}}
@foreach ($products as $product)
    <x-product-card :product="$product" />
@endforeach
