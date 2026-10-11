@extends('layouts.shop')

@section('title', 'المفضّلة')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">المفضّلة</h1>

    @if ($products->isEmpty())
        <x-empty title="لا منتجات في المفضّلة"
                 description="اضغط على القلب في أي منتج لحفظه هنا."
                 :action="route('catalog')" />
    @else
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
@endsection
