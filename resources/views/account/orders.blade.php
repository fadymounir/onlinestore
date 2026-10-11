@extends('layouts.shop')

@section('title', 'طلباتي')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">طلباتي</h1>

    @if ($orders->isEmpty())
        <x-empty title="لا طلبات بعد"
                 description="عند إتمام أول طلب سيظهر هنا مع حالة الشحن."
                 :action="route('catalog')" />
    @else
        <div class="space-y-4">
            @foreach ($orders as $order)
                <a href="{{ route('account.orders.show', $order) }}"
                   class="block rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-stone-400">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-bold text-stone-900" dir="ltr">{{ $order->order_number }}</p>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ $order->placed_at?->translatedFormat('j F Y') }} · {{ $order->items_count }} منتج
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                @class([
                                    'bg-amber-100 text-amber-800' => $order->status->color() === 'warning',
                                    'bg-sky-100 text-sky-800' => $order->status->color() === 'info',
                                    'bg-stone-200 text-stone-800' => in_array($order->status->color(), ['primary', 'gray']),
                                    'bg-emerald-100 text-emerald-800' => $order->status->color() === 'success',
                                    'bg-red-100 text-red-800' => $order->status->color() === 'danger',
                                ])">
                                {{ $order->status->label() }}
                            </span>
                            <span class="font-bold text-stone-900">{{ number_format((float) $order->grand_total, 2) }} ج.م</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-10">{{ $orders->links() }}</div>
    @endif
@endsection
