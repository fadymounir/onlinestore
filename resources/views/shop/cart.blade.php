@extends('layouts.shop')

@section('title', 'سلة الشراء')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">سلة الشراء</h1>

    @if ($lines->isEmpty())
        <x-empty title="سلتك فارغة"
                 description="أضف بعض المنتجات ثم أكمل الطلب بالدفع عند الاستلام."
                 :action="route('catalog')" />
    @else
        @php $discount = $coupon ? $coupon->discountFor($subtotal) : 0; @endphp

        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            {{-- lines --}}
            <div class="space-y-4">
                @foreach ($lines as $line)
                    @php $variant = $line['variant']; $product = $variant->product; @endphp

                    <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-stone-200 bg-white p-4">
                        <a href="{{ route('product', $product) }}" class="shrink-0">
                            @if ($image = $variant->image ?? $product->primaryImage?->path)
                                <img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $product->name }}"
                                     class="size-24 rounded-xl border border-stone-200 object-cover">
                            @else
                                <span class="flex size-24 items-center justify-center rounded-xl bg-stone-100 text-xs text-stone-400">بلا صورة</span>
                            @endif
                        </a>

                        <div class="min-w-40 flex-1">
                            <h2 class="font-semibold text-stone-900">
                                <a href="{{ route('product', $product) }}" class="hover:text-amber-700">{{ $product->name }}</a>
                            </h2>
                            @if ($label = $variant->label())
                                <p class="mt-1 text-sm text-stone-500">{{ $label }}</p>
                            @endif
                            <p class="mt-1 text-xs text-stone-400" dir="ltr">{{ $variant->sku }}</p>

                            @if ($variant->stock_quantity < $line['quantity'])
                                <p class="mt-2 text-xs font-medium text-red-700">
                                    المتاح الآن {{ $variant->stock_quantity }} فقط — عدّل الكمية.
                                </p>
                            @endif
                        </div>

                        <form action="{{ route('cart.update', $variant) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="1"
                                   max="{{ max(1, min(99, $variant->stock_quantity)) }}"
                                   class="w-16 rounded-lg border border-stone-300 px-2 py-2 text-center text-sm outline-none focus:border-amber-600">
                            <button type="submit" class="rounded-lg border border-stone-300 px-3 py-2 text-xs text-stone-700 hover:bg-stone-100">
                                تحديث
                            </button>
                        </form>

                        <div class="text-end">
                            <x-price :amount="(float) $variant->price * $line['quantity']" />
                            <p class="mt-1 text-xs text-stone-400">{{ number_format((float) $variant->price, 2) }} × {{ $line['quantity'] }}</p>
                        </div>

                        <form action="{{ route('cart.remove', $variant) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="إزالة"
                                    class="rounded-lg p-2 text-stone-400 hover:bg-red-50 hover:text-red-600">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.67.1 1.01.16m-1 0L19.23 19.3a2.25 2.25 0 0 1-2.24 2.08H7.01a2.25 2.25 0 0 1-2.24-2.08L3.76 5.93m15.47 0a48 48 0 0 0-3.48-.4m-9.99.4c.34-.06.67-.11 1.01-.16m8.98.56V4.48c0-1.14-.88-2.1-2.02-2.13a52 52 0 0 0-3.48 0c-1.14.03-2.02 1-2.02 2.13v1.3m7.52 0a48 48 0 0 0-7.52 0"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            {{-- summary --}}
            <aside class="space-y-4">
                <div class="rounded-2xl border border-stone-200 bg-white p-5">
                    <h2 class="mb-4 font-bold text-stone-900">ملخص الطلب</h2>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-stone-600">إجمالي المنتجات</dt>
                            <dd class="font-medium">{{ number_format($subtotal, 2) }} ج.م</dd>
                        </div>

                        @if ($discount > 0)
                            <div class="flex items-center justify-between text-emerald-700">
                                <dt>خصم ({{ $coupon->code }})</dt>
                                <dd class="font-medium">−{{ number_format($discount, 2) }} ج.م</dd>
                            </div>
                        @endif

                        <div class="flex items-center justify-between text-stone-500">
                            <dt>الشحن</dt>
                            <dd>يُحسب عند اختيار العنوان</dd>
                        </div>

                        <div class="flex items-center justify-between border-t border-stone-200 pt-3 text-base">
                            <dt class="font-bold text-stone-900">الإجمالي</dt>
                            <dd class="font-bold text-stone-900">{{ number_format($subtotal - $discount, 2) }} ج.م</dd>
                        </div>
                    </dl>

                    @auth
                        <a href="{{ route('checkout') }}"
                           class="mt-5 block rounded-lg bg-amber-600 px-6 py-3 text-center font-semibold text-white hover:bg-amber-700">
                            إتمام الطلب
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="mt-5 block rounded-lg bg-amber-600 px-6 py-3 text-center font-semibold text-white hover:bg-amber-700">
                            سجّل الدخول لإتمام الطلب
                        </a>
                        <p class="mt-2 text-center text-xs text-stone-500">سلتك محفوظة وستنتقل معك بعد الدخول.</p>
                    @endauth

                    <a href="{{ route('catalog') }}" class="mt-3 block text-center text-sm text-stone-600 hover:text-amber-700">
                        متابعة التسوّق
                    </a>
                </div>

                {{-- coupon --}}
                <div class="rounded-2xl border border-stone-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-bold text-stone-900">كوبون الخصم</h2>

                    @if ($coupon)
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-3 py-2.5">
                            <span class="text-sm font-semibold text-emerald-800" dir="ltr">{{ $coupon->code }}</span>
                            <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-700 hover:underline">إلغاء</button>
                            </form>
                        </div>
                    @else
                        <form action="{{ route('cart.coupon.apply') }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="text" name="code" value="{{ $couponCode }}" placeholder="أدخل الكود" dir="ltr"
                                   class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                            <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800">
                                تطبيق
                            </button>
                        </form>
                        @guest
                            <p class="mt-2 text-xs text-stone-500">سجّل الدخول لاستخدام الكوبونات.</p>
                        @endguest
                    @endif
                </div>
            </aside>
        </div>
    @endif
@endsection
