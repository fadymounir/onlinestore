@extends('layouts.shop')

@section('title', 'الطلب ' . $order->order_number)

@php use App\Enums\OrderStatus; @endphp

@section('content')
    <nav class="mb-4 text-sm text-stone-500">
        <a href="{{ route('account.orders.index') }}" class="hover:text-amber-700">طلباتي</a>
        <span class="mx-2">/</span>
        <span class="text-stone-900" dir="ltr">{{ $order->order_number }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900" dir="ltr">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-stone-500">
                طُلب في {{ $order->placed_at?->translatedFormat('j F Y — H:i') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1.5 text-sm font-semibold
                @class([
                    'bg-amber-100 text-amber-800' => $order->status->color() === 'warning',
                    'bg-sky-100 text-sky-800' => $order->status->color() === 'info',
                    'bg-stone-200 text-stone-800' => in_array($order->status->color(), ['primary', 'gray']),
                    'bg-emerald-100 text-emerald-800' => $order->status->color() === 'success',
                    'bg-red-100 text-red-800' => $order->status->color() === 'danger',
                ])">
                {{ $order->status->label() }}
            </span>
            <span class="rounded-full bg-stone-100 px-3 py-1.5 text-sm text-stone-700">
                {{ $order->payment_status->label() }} · {{ $order->payment_method->label() }}
            </span>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            {{-- items --}}
            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="mb-4 font-bold text-stone-900">المنتجات</h2>
                <ul class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <span class="flex-1">
                                <span class="block font-medium text-stone-900">
                                    @if ($item->product_id)
                                        <a href="{{ route('product', $item->product) }}" class="hover:text-amber-700">{{ $item->product_name }}</a>
                                    @else
                                        {{ $item->product_name }}
                                    @endif
                                </span>
                                <span class="text-stone-500">
                                    {{ $item->variant_label ?: '—' }} · ×{{ $item->quantity }}
                                    · <span dir="ltr">{{ $item->sku }}</span>
                                </span>
                            </span>
                            <span class="font-semibold">{{ number_format((float) $item->line_total, 2) }} ج.م</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- timeline --}}
            @if ($order->statusHistories->isNotEmpty())
                <section class="rounded-2xl border border-stone-200 bg-white p-5">
                    <h2 class="mb-4 font-bold text-stone-900">تتبّع الطلب</h2>
                    <ol class="space-y-4">
                        @foreach ($order->statusHistories->sortByDesc('id') as $history)
                            <li class="flex gap-3">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-amber-500"></span>
                                <span class="text-sm">
                                    <span class="block font-medium text-stone-900">{{ $history->to_status->label() }}</span>
                                    <span class="text-stone-500">{{ $history->created_at->translatedFormat('j F Y — H:i') }}</span>
                                    @if ($history->note)
                                        <span class="mt-1 block text-stone-600">{{ $history->note }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            {{-- cancel --}}
            @if ($order->status->canMoveTo(OrderStatus::Cancelled))
                <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <h2 class="mb-2 font-bold text-red-900">إلغاء الطلب</h2>
                    <p class="mb-4 text-sm text-red-800">
                        يمكنك الإلغاء قبل شحن الطلب. بعد الشحن تواصل معنا.
                    </p>
                    <form action="{{ route('account.orders.cancel', $order) }}" method="POST" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="text" name="reason" required maxlength="255" placeholder="سبب الإلغاء"
                               class="flex-1 rounded-lg border border-red-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-red-500">
                        <button type="submit"
                                onclick="return confirm('هل أنت متأكد من إلغاء الطلب؟')"
                                class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                            إلغاء الطلب
                        </button>
                    </form>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="mb-3 font-bold text-stone-900">عنوان التوصيل</h2>
                <p class="text-sm font-medium text-stone-900">{{ $order->recipient_name }}</p>
                <p class="mt-1 text-sm text-stone-600">
                    {{ collect([$order->street, $order->building ? "عقار {$order->building}" : null,
                                $order->floor ? "الدور {$order->floor}" : null,
                                $order->apartment ? "شقة {$order->apartment}" : null,
                                $order->city_name, $order->governorate_name])->filter()->implode('، ') }}
                </p>
                @if ($order->landmark)
                    <p class="mt-1 text-xs text-stone-500">{{ $order->landmark }}</p>
                @endif
                <p class="mt-2 text-sm text-stone-600" dir="ltr">{{ $order->phone }}</p>
            </section>

            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="mb-4 font-bold text-stone-900">الحساب</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-stone-600">إجمالي المنتجات</dt>
                        <dd>{{ number_format((float) $order->subtotal, 2) }} ج.م</dd>
                    </div>
                    @if ((float) $order->discount_total > 0)
                        <div class="flex justify-between text-emerald-700">
                            <dt>خصم @if ($order->coupon_code)({{ $order->coupon_code }})@endif</dt>
                            <dd>−{{ number_format((float) $order->discount_total, 2) }} ج.م</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-stone-600">الشحن</dt>
                        <dd>
                            @if ((float) $order->shipping_total <= 0)
                                <span class="text-emerald-700">مجاني</span>
                            @else
                                {{ number_format((float) $order->shipping_total, 2) }} ج.م
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-stone-200 pt-3 text-base font-bold text-stone-900">
                        <dt>الإجمالي</dt>
                        <dd>{{ number_format((float) $order->grand_total, 2) }} ج.م</dd>
                    </div>
                </dl>
            </section>

            @if ($order->customer_note)
                <section class="rounded-2xl border border-stone-200 bg-white p-5">
                    <h2 class="mb-2 font-bold text-stone-900">ملاحظتك</h2>
                    <p class="text-sm text-stone-600">{{ $order->customer_note }}</p>
                </section>
            @endif
        </aside>
    </div>
@endsection
