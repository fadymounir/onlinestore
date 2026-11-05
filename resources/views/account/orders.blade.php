@extends($shopLayout ?? 'layouts.shop')

@section('title', 'طلباتي')

@section('content')
    <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">طلباتي</h1>

    @if ($orders->isEmpty())
        <x-empty title="لا طلبات بعد"
                 description="عند إتمام أول طلب سيظهر هنا مع حالة الشحن."
                 :action="route('catalog')" />
    @else
        <div class="space-y-4">
            @foreach ($orders as $order)
                <a href="{{ route('account.orders.show', $order) }}"
                   class="block rounded-3xl bg-white p-6 shadow-soft transition hover:shadow-lift">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-bold text-stone-900" dir="ltr">{{ $order->order_number }}</p>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ $order->placed_at?->translatedFormat('j F Y') }} · {{ $order->items_count }} منتج
                            </p>
                            @if ($order->awaitingRefund())
                                <p class="mt-1 text-xs font-semibold text-brand-800">
                                    جارٍ رد {{ number_format($order->refundableAmount(), 2) }} ج.م
                                </p>
                            @elseif ($order->isRefunded())
                                <p class="mt-1 text-xs text-emerald-800">
                                    تم رد {{ number_format($order->refundedAmount(), 2) }} ج.م
                                </p>
                            @elseif ($order->isDeposit())
                                <p class="mt-1 text-xs text-brand-800">
                                    {{ $order->deposit_status?->label() }} ·
                                    المتبقي {{ number_format($order->remainingAmount(), 2) }} ج.م
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                @class([
                                    'bg-brand-100 text-brand-800' => $order->status->color() === 'warning',
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
