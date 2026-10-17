@extends('layouts.shop')

@section('title', 'سلة الشراء')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">سلة الشراء</h1>

    {{--
        The id is the swap target: every cart mutation returns this partial
        re-rendered, so quantities, totals, the discount row and the coupon box
        all stay consistent with one replacement.
    --}}
    <div id="cart-panel" data-ajax-region>
        @include('shop.partials.cart-panel')
    </div>
@endsection
