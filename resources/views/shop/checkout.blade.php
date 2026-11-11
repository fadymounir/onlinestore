@extends($shopLayout ?? 'layouts.shop')

@section('title', 'إتمام الطلب')

@section('content')
    <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">إتمام الطلب</h1>

    @php use App\Enums\PaymentMethod; @endphp

    {{-- enctype: the deposit option carries a transfer receipt. --}}
    <form action="{{ route('checkout.store') }}" method="POST" enctype="multipart/form-data"
          class="grid gap-8 lg:grid-cols-[1fr_360px]">
        @csrf

        <div class="space-y-6">
            {{-- address --}}
            @if (! $collectsAddress)
                {{-- Addresses are switched off in the panel, so the step is
                     gone rather than shown empty. The order still carries the
                     account's name and phone, which is what the shop rings. --}}
                <section class="rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-3 font-bold text-stone-900">التوصيل</h2>
                    <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm leading-relaxed text-brand-900">
                        سنتواصل معك على رقم حسابك لتأكيد الطلب والاتفاق على عنوان ووقت التوصيل،
                        وتُحسب تكلفة الشحن وقتها.
                    </p>
                </section>
            @else
            <section class="rounded-3xl bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-bold text-stone-900">عنوان التوصيل</h2>
                    <a href="{{ route('account.addresses.index') }}" class="text-sm font-medium text-brand-700 hover:underline">
                        إدارة العناوين
                    </a>
                </div>

                @if ($addresses->isEmpty())
                    <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">
                        لا يوجد عنوان محفوظ.
                        <a href="{{ route('account.addresses.index') }}" class="font-semibold underline">أضف عنواناً</a>
                        لإتمام الطلب.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach ($addresses as $option)
                            <label @class([
                                'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                                'border-brand-600 bg-brand-50/50' => $address && $option->id === $address->id,
                                'border-stone-200 hover:shadow-lift' => ! $address || $option->id !== $address->id,
                            ])>
                                <input type="radio" name="address_id" value="{{ $option->id }}"
                                       @checked($address && $option->id === $address->id)
                                       class="mt-1 size-4 accent-brand-600">
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
            @endif

            {{-- payment --}}
            <section class="rounded-3xl bg-white p-6 shadow-soft">
                <h2 class="mb-4 font-bold text-stone-900">طريقة الدفع</h2>

                @if (empty($methods))
                    <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                        لا توجد طريقة دفع متاحة حالياً. تواصل معنا لإتمام الطلب.
                    </p>
                @else
                    {{-- No framework here: the panel below is plain markup that
                         shows by default and is hidden by CSS (:has) once
                         another method is picked, so it works with JS off and
                         on a browser without :has() alike. --}}
                    <div class="payment-methods space-y-3">
                        @foreach ($methods as $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition">
                                <input type="radio" name="payment_method" value="{{ $option->value }}"
                                       @checked($selectedMethod === $option)
                                       class="mt-1 size-4 accent-brand-600">
                                <span class="text-sm">
                                    <span class="block font-semibold text-stone-900">
                                        {{ $option->label() }}
                                        @if ($option === PaymentMethod::Deposit)
                                            <span class="ms-1 rounded bg-brand-100 px-1.5 py-0.5 text-[11px] font-bold text-brand-800">
                                                من {{ rtrim(rtrim(number_format($totals['deposit_percent'], 2), '0'), '.') }}%
                                            </span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-stone-600">{{ $option->description() }}</span>
                                </span>
                            </label>
                        @endforeach

                        @if (in_array(PaymentMethod::Deposit, $methods, true))
                            @php
                                [$minTransfer, $maxTransfer] = $totals['deposit_window'];
                                // Left empty on purpose: the customer types what they
                                // actually transferred, rather than confirming a guess.
                                $transferred = old('transfer_amount', '');
                            @endphp

                            <div class="payment-deposit rounded-2xl border border-brand-200 bg-brand-50/60 p-4"
                                 data-deposit data-receipt-scope
                                 data-deposit-min="{{ $minTransfer }}" data-deposit-total="{{ $totals['total'] }}">
                                <dl class="mb-4 space-y-2 text-sm">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-stone-600">أقل مبلغ مطلوب الآن</dt>
                                        <dd class="font-bold text-stone-900">{{ number_format($minTransfer) }} ج.م</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-stone-600">إجمالي الطلب</dt>
                                        <dd class="font-medium text-stone-900">{{ number_format($totals['total'], 2) }} ج.م</dd>
                                    </div>
                                </dl>

                                {{-- The customer names what they actually sent: the minimum share,
                                     the whole order, or anything in between. --}}
                                <label class="block text-sm font-semibold text-stone-900" for="transfer_amount">
                                    المبلغ الذي حوّلته <span class="text-red-600">*</span>
                                </label>
                                <div class="mt-2 flex items-center gap-2">
                                    <input id="transfer_amount" type="number" name="transfer_amount" inputmode="numeric"
                                           step="1" min="{{ $minTransfer }}" max="{{ $maxTransfer }}"
                                           value="{{ $transferred }}" dir="ltr" data-deposit-amount data-deposit-required
                                           placeholder="{{ $minTransfer }}"
                                           class="w-40 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-500/10">
                                    <span class="text-sm text-stone-600">ج.م</span>
                                </div>
                                <p class="mt-1 text-xs text-stone-500">
                                    بالجنيه الصحيح، من {{ number_format($minTransfer) }} ج.م
                                    ({{ rtrim(rtrim(number_format($totals['deposit_percent'], 2), '0'), '.') }}%)
                                    وحتى قيمة الطلب كاملة {{ number_format($maxTransfer) }} ج.م.
                                </p>
                                @error('transfer_amount')
                                    <p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>
                                @enderror

                                <p class="mt-3 rounded-xl bg-white px-4 py-3 text-sm text-stone-700" data-deposit-remaining>
                                    المتبقي عند الاستلام
                                    <span class="font-bold text-stone-900">{{ number_format(max(0, $totals['total'] - (float) $transferred), 2) }} ج.م</span>
                                </p>

                                @if (filled($transferInstructions))
                                    <p class="my-4 whitespace-pre-line rounded-xl bg-white px-4 py-3 text-sm text-stone-700">{{ $transferInstructions }}</p>
                                @endif

                                <label class="block text-sm font-semibold text-stone-900" for="deposit_receipt">
                                    إيصال التحويل <span class="text-red-600">*</span>
                                </label>
                                <input id="deposit_receipt" type="file" name="deposit_receipt"
                                       accept="image/jpeg,image/png,image/webp,application/pdf"
                                       data-receipt-input data-deposit-required
                                       class="mt-2 w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-stone-900 file:px-3 file:py-1.5 file:text-white">
                                <p class="mt-1 text-xs text-stone-500">صورة أو ملف PDF، حتى 5 ميجابايت.</p>
                                @error('deposit_receipt')
                                    <p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>
                                @enderror

                                {{-- The admin approves the deposit from this very file, so the
                                     customer gets to look at it before sending. Filled in by
                                     JS; with JS off the input alone still works. --}}
                                <figure class="mt-3 rounded-xl border border-stone-200 bg-white p-3" data-receipt-preview hidden>
                                    <img alt="معاينة إيصال التحويل" data-receipt-image
                                         class="mx-auto max-h-72 rounded-lg object-contain" hidden>
                                    <a target="_blank" rel="noopener" data-receipt-file
                                       class="block break-all text-center text-sm font-semibold text-brand-700 hover:underline" hidden></a>
                                    <figcaption class="mt-2 text-center text-xs text-stone-500" data-receipt-caption></figcaption>
                                </figure>

                                <label class="mt-4 block text-sm font-semibold text-stone-900" for="transfer_reference">
                                    رقم عملية التحويل <span class="font-normal text-stone-500">(اختياري)</span>
                                </label>
                                <input id="transfer_reference" type="text" name="transfer_reference" maxlength="100"
                                       value="{{ old('transfer_reference') }}" dir="ltr"
                                       class="mt-2 w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-500/10">

                                <p class="mt-4 text-xs text-stone-600">
                                    يبدأ تجهيز الطلب بعد مراجعة الإيصال واعتماد العربون.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            {{-- note --}}
            <section class="rounded-3xl bg-white p-6 shadow-soft">
                <h2 class="mb-3 font-bold text-stone-900">ملاحظات</h2>
                <textarea name="note" rows="3" maxlength="1000" placeholder="مثال: اتصل قبل التوصيل بنصف ساعة."
                          class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">{{ old('note') }}</textarea>
            </section>
        </div>

        {{-- summary --}}
        <aside class="h-fit rounded-3xl bg-white p-6 shadow-soft">
            <h2 class="mb-4 font-bold text-stone-900">مراجعة الطلب</h2>

            <ul class="mb-4 space-y-3 border-b border-stone-200 pb-4">
                @foreach ($lines as $line)
                    <li class="flex items-start justify-between gap-3 text-sm">
                        <span class="flex-1">
                            <span class="block font-medium text-stone-900">{{ $line['variant']->product->name }}</span>
                            {{-- A variant with no attributes has no label, and
                                 the separator would hang there on its own. --}}
                            <span class="text-stone-500">
                                @if ($label = $line['variant']->label()){{ $label }} · @endif×{{ $line['quantity'] }}
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
                        @if (! $collectsAddress)
                            {{-- Zero here means "no address to price it from",
                                 not "free" — saying مجاني would be a promise
                                 the shop has not made. --}}
                            <span class="text-stone-500">يُحسب عند التأكيد</span>
                        @elseif ($totals['shipping'] <= 0)
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
                    <dt class="font-bold text-stone-900">إجمالي الطلب</dt>
                    <dd class="font-bold text-stone-900">{{ number_format($totals['total'], 2) }} ج.م</dd>
                </div>
            </dl>

            <button type="submit" @disabled($addresses->isEmpty() || empty($methods))
                    class="mt-5 w-full rounded-xl bg-stone-900 px-6 py-3 font-bold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300">
                تأكيد الطلب
            </button>

            <p class="mt-3 text-center text-xs text-stone-500">
                بتأكيد الطلب أنت توافق على سداد المبلغ المطلوب حسب طريقة الدفع المختارة.
            </p>
        </aside>
    </form>
@endsection
