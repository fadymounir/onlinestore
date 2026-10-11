@extends('layouts.shop')

@section('title', 'إتمام الطلب')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">إتمام الطلب</h1>

    <form action="{{ route('checkout.store') }}" method="POST" class="grid gap-8 lg:grid-cols-[1fr_360px]">
        @csrf

        <div class="space-y-6">
            {{-- address --}}
            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-bold text-stone-900">عنوان التوصيل</h2>
                    <a href="{{ route('account.addresses.index') }}" class="text-sm font-medium text-amber-700 hover:underline">
                        إدارة العناوين
                    </a>
                </div>

                @if ($addresses->isEmpty())
                    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        لا يوجد عنوان محفوظ.
                        <a href="{{ route('account.addresses.index') }}" class="font-semibold underline">أضف عنواناً</a>
                        لإتمام الطلب.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach ($addresses as $option)
                            <label @class([
                                'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                                'border-amber-600 bg-amber-50/50' => $address && $option->id === $address->id,
                                'border-stone-200 hover:border-stone-400' => ! $address || $option->id !== $address->id,
                            ])>
                                <input type="radio" name="address_id" value="{{ $option->id }}"
                                       @checked($address && $option->id === $address->id)
                                       class="mt-1 size-4 border-stone-300 text-amber-600 focus:ring-amber-600">
                                <span class="text-sm">
                                    <span class="block font-semibold text-stone-900">
                                        {{ $option->recipient_name }}
                                        @if ($option->label)
                                            <span class="ms-2 rounded bg-stone-100 px-1.5 py-0.5 text-[11px] font-medium text-stone-600">{{ $option->label }}</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-stone-600">{{ $option->fullAddress() }}</span>
                                    <span class="mt-1 block text-stone-500" dir="ltr">{{ $option->phone }}</span>
                                    @if ($option->landmark)
                                        <span class="mt-1 block text-xs text-stone-400">{{ $option->landmark }}</span>
                                    @endif
                                    @unless ($option->governorate?->shippingZone)
                                        <span class="mt-2 block text-xs font-medium text-red-700">لا يوجد شحن متاح لهذه المحافظة حالياً.</span>
                                    @endunless
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-stone-500">
                        سعر الشحن محسوب على المحافظة المحدّدة حالياً. اختيار عنوان في محافظة أخرى قد يغيّره — احفظه كعنوان افتراضي لتحديث الحساب.
                    </p>
                @endif
            </section>

            {{-- payment --}}
            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="mb-4 font-bold text-stone-900">طريقة الدفع</h2>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-600 bg-amber-50/50 p-4">
                    <input type="radio" name="payment_method" value="cod" checked
                           class="mt-1 size-4 border-stone-300 text-amber-600 focus:ring-amber-600">
                    <span class="text-sm">
                        <span class="block font-semibold text-stone-900">الدفع عند الاستلام</span>
                        <span class="mt-1 block text-stone-600">تدفع نقداً للمندوب وقت تسليم الطلب.</span>
                    </span>
                </label>
            </section>

            {{-- note --}}
            <section class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="mb-3 font-bold text-stone-900">ملاحظة للمندوب</h2>
                <textarea name="note" rows="3" maxlength="1000" placeholder="مثال: اتصل قبل التوصيل بنصف ساعة."
                          class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">{{ old('note') }}</textarea>
            </section>
        </div>

        {{-- summary --}}
        <aside class="h-fit rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-4 font-bold text-stone-900">مراجعة الطلب</h2>

            <ul class="mb-4 space-y-3 border-b border-stone-200 pb-4">
                @foreach ($lines as $line)
                    <li class="flex items-start justify-between gap-3 text-sm">
                        <span class="flex-1">
                            <span class="block font-medium text-stone-900">{{ $line['variant']->product->name }}</span>
                            <span class="text-stone-500">
                                {{ $line['variant']->label() }} · ×{{ $line['quantity'] }}
                            </span>
                        </span>
                        <span class="whitespace-nowrap font-medium">
                            {{ number_format((float) $line['variant']->price * $line['quantity'], 2) }} ج.م
                        </span>
                    </li>
                @endforeach
            </ul>

            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-stone-600">إجمالي المنتجات</dt>
                    <dd class="font-medium">{{ number_format($totals['subtotal'], 2) }} ج.م</dd>
                </div>

                @if ($totals['discount'] > 0)
                    <div class="flex items-center justify-between text-emerald-700">
                        <dt>خصم ({{ $totals['coupon']->code }})</dt>
                        <dd class="font-medium">−{{ number_format($totals['discount'], 2) }} ج.م</dd>
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <dt class="text-stone-600">
                        الشحن
                        @if ($address?->governorate)
                            <span class="block text-xs text-stone-400">{{ $address->governorate->name }}</span>
                        @endif
                    </dt>
                    <dd class="font-medium">
                        @if ($totals['shipping'] <= 0)
                            <span class="text-emerald-700">مجاني</span>
                        @else
                            {{ number_format($totals['shipping'], 2) }} ج.م
                        @endif
                    </dd>
                </div>

                @if ($zone = $address?->governorate?->shippingZone)
                    <p class="text-xs text-stone-500">
                        التوصيل من {{ $zone->delivery_days_min }} إلى {{ $zone->delivery_days_max }} يوم عمل.
                    </p>
                @endif

                <div class="flex items-center justify-between border-t border-stone-200 pt-3 text-lg">
                    <dt class="font-bold text-stone-900">المطلوب دفعه</dt>
                    <dd class="font-bold text-stone-900">{{ number_format($totals['total'], 2) }} ج.م</dd>
                </div>
            </dl>

            <button type="submit" @disabled($addresses->isEmpty())
                    class="mt-5 w-full rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-stone-300">
                تأكيد الطلب
            </button>

            <p class="mt-3 text-center text-xs text-stone-500">
                بتأكيد الطلب أنت توافق على الدفع نقداً عند الاستلام.
            </p>
        </aside>
    </form>
@endsection
