@extends($shopLayout ?? 'layouts.shop')

@section('title', 'الطلب ' . $order->order_number)

@php use App\Enums\OrderStatus; @endphp

@section('content')
    <nav class="mb-4 text-sm text-stone-500">
        <a href="{{ route('account.orders.index') }}" class="hover:text-brand-700">طلباتي</a>
        <span class="mx-2">/</span>
        <span class="text-stone-900" dir="ltr">{{ $order->order_number }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl" dir="ltr">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-stone-500">
                طُلب في {{ $order->placed_at?->translatedFormat('j F Y — H:i') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1.5 text-sm font-semibold
                @class([
                    'bg-brand-100 text-brand-800' => $order->status->color() === 'warning',
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
            @if ($order->isDeposit() && $order->deposit_status)
                <span class="rounded-full px-3 py-1.5 text-sm font-semibold
                    @class([
                        'bg-brand-100 text-brand-800' => $order->deposit_status->color() === 'warning',
                        'bg-emerald-100 text-emerald-800' => $order->deposit_status->color() === 'success',
                        'bg-red-100 text-red-800' => $order->deposit_status->color() === 'danger',
                    ])">
                    {{ $order->deposit_status->label() }}
                </span>
            @endif
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            {{-- items --}}
            <section class="rounded-3xl bg-white p-6 shadow-soft">
                <h2 class="mb-4 font-bold text-stone-900">المنتجات</h2>
                <ul class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <span class="flex-1">
                                <span class="block font-medium text-stone-900">
                                    @if ($item->product_id)
                                        <a href="{{ route('product', $item->product) }}" class="hover:text-brand-700">{{ $item->product_name }}</a>
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


            {{-- deposit --}}
            @if ($order->isDeposit())
                <section class="rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-4 font-bold text-stone-900">العربون المقدّم</h2>

                    <dl class="mb-5 grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-2xl bg-stone-50 p-4">
                            <dt class="text-stone-600">إجمالي الطلب</dt>
                            <dd class="mt-1 font-bold text-stone-900">{{ number_format((float) $order->grand_total, 2) }} ج.م</dd>
                        </div>
                        <div class="rounded-2xl bg-brand-50 p-4">
                            <dt class="text-brand-900">
                                المبلغ المحوَّل
                                @if ($order->deposit_percent)
                                    <span class="text-xs text-brand-700">({{ rtrim(rtrim(number_format((float) $order->deposit_percent, 2), '0'), '.') }}%)</span>
                                @endif
                            </dt>
                            <dd class="mt-1 font-bold text-brand-900">{{ number_format($order->depositAmount(), 2) }} ج.م</dd>
                        </div>
                        @if ($order->isVoided())
                            {{-- Nothing left to collect on a cancelled order; what matters is the way back. --}}
                            <div class="rounded-2xl bg-stone-50 p-4">
                                <dt class="text-stone-600">{{ $order->isRefunded() ? 'المبلغ المسترد' : 'مستحق الاسترداد' }}</dt>
                                <dd class="mt-1 font-bold text-stone-900">
                                    {{ number_format($order->isRefunded() ? $order->refundedAmount() : $order->refundableAmount(), 2) }} ج.م
                                </dd>
                            </div>
                        @else
                            <div class="rounded-2xl bg-stone-50 p-4">
                                <dt class="text-stone-600">المتبقي عند الاستلام</dt>
                                <dd class="mt-1 font-bold text-stone-900">{{ number_format($order->remainingAmount(), 2) }} ج.م</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($order->awaitingDepositReview())
                        <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">
                            استلمنا إيصال التحويل وهو قيد المراجعة. سنؤكد الطلب بعد اعتماد العربون.
                        </p>
                    @elseif ($order->depositApproved() && ! $order->isVoided())
                        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            تم اعتماد العربون في {{ $order->deposit_reviewed_at?->translatedFormat('j F Y — H:i') }}.
                            المتبقي {{ number_format($order->remainingAmount(), 2) }} ج.م يُدفع للمندوب عند الاستلام.
                        </p>
                    @elseif ($order->depositRejected())
                        <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-900">
                            لم نتمكن من اعتماد إيصال التحويل.
                            @if ($order->deposit_rejection_reason)
                                السبب: {{ $order->deposit_rejection_reason }}
                            @endif
                            يمكنك رفع إيصال آخر من هنا.
                        </p>
                    @endif

                    @if ($url = $order->depositReceiptUrl())
                        <p class="mt-4 text-sm">
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="font-semibold text-brand-700 hover:underline">عرض الإيصال المرفوع</a>
                            @if ($order->deposit_reference)
                                <span class="text-stone-500">· رقم العملية <span dir="ltr">{{ $order->deposit_reference }}</span></span>
                            @endif
                        </p>
                    @endif

                    @if ($order->canReplaceDepositReceipt())
                        <form action="{{ route('account.orders.deposit-receipt', $order) }}" method="POST"
                              enctype="multipart/form-data" data-receipt-scope
                              class="mt-5 space-y-3 border-t border-stone-200 pt-5">
                            @csrf
                            <label class="block text-sm font-semibold text-stone-900" for="deposit_receipt">
                                رفع إيصال تحويل {{ $order->deposit_receipt_path ? 'بديل' : '' }}
                                <span class="text-red-600">*</span>
                            </label>
                            <input id="deposit_receipt" type="file" name="deposit_receipt" required
                                   accept="image/jpeg,image/png,image/webp,application/pdf" data-receipt-input
                                   class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-stone-900 file:px-3 file:py-1.5 file:text-white">
                            @error('deposit_receipt')
                                <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                            @enderror

                            {{-- What the admin will be looking at, shown before it is sent. --}}
                            <figure class="rounded-xl border border-stone-200 bg-white p-3" data-receipt-preview hidden>
                                <img alt="معاينة إيصال التحويل" data-receipt-image
                                     class="mx-auto max-h-72 rounded-lg object-contain" hidden>
                                <a target="_blank" rel="noopener" data-receipt-file
                                   class="block break-all text-center text-sm font-semibold text-brand-700 hover:underline" hidden></a>
                                <figcaption class="mt-2 text-center text-xs text-stone-500" data-receipt-caption></figcaption>
                            </figure>

                            <label class="block text-sm font-semibold text-stone-900" for="transfer_amount">
                                المبلغ الذي حوّلته <span class="text-red-600">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input id="transfer_amount" type="number" name="transfer_amount" inputmode="numeric"
                                       step="1" min="{{ $transferWindow[0] }}" max="{{ $transferWindow[1] }}"
                                       value="{{ old('transfer_amount', '') }}" placeholder="{{ $transferWindow[0] }}"
                                       required dir="ltr"
                                       class="w-40 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:bg-white">
                                <span class="text-sm text-stone-600">ج.م</span>
                            </div>
                            <p class="text-xs text-stone-500">
                                بالجنيه الصحيح، من {{ number_format($transferWindow[0]) }} ج.م وحتى قيمة الطلب كاملة
                                {{ number_format($transferWindow[1]) }} ج.م.
                            </p>
                            @error('transfer_amount')
                                <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                            @enderror

                            <input type="text" name="transfer_reference" maxlength="100" dir="ltr"
                                   placeholder="رقم عملية التحويل (اختياري)"
                                   value="{{ $order->deposit_reference }}"
                                   class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:bg-white">

                            <button type="submit"
                                    class="rounded-xl bg-stone-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-stone-800">
                                إرسال الإيصال
                            </button>
                        </form>
                    @endif
                </section>
            @endif

            {{-- refund --}}
            @if ($order->awaitingRefund() || $order->isRefunded())
                <section class="rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-4 font-bold text-stone-900">استرداد المبلغ</h2>

                    @if ($order->isRefunded())
                        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm leading-relaxed text-emerald-900">
                            تم رد {{ number_format($order->refundedAmount(), 2) }} ج.م
                            @if ($order->refunded_at)
                                بتاريخ {{ $order->refunded_at->translatedFormat('j F Y — H:i') }}
                            @endif
                            @if ($order->refund_note)
                                — {{ $order->refund_note }}
                            @endif
                        </p>

                        @if ($order->refund_reference)
                            <p class="mt-3 text-xs text-stone-500">
                                رقم عملية التحويل <span dir="ltr">{{ $order->refund_reference }}</span>
                            </p>
                        @endif
                    @else
                        <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm leading-relaxed text-brand-900">
                            المبلغ الذي دفعته ({{ number_format($order->refundableAmount(), 2) }} ج.م) مستحق لك
                            وجارٍ تحويله على نفس الوسيلة التي حوّلت منها. لو تأخر أكثر من ثلاثة أيام عمل تواصل معنا.
                        </p>
                    @endif
                </section>
            @endif

            {{-- timeline --}}
            @if ($order->statusHistories->isNotEmpty())
                <section class="rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-4 font-bold text-stone-900">تتبّع الطلب</h2>
                    <ol class="space-y-4">
                        @foreach ($order->statusHistories->sortByDesc('id') as $history)
                            <li class="flex gap-3">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>
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
            <section class="rounded-3xl bg-white p-6 shadow-soft">
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

            <section class="rounded-3xl bg-white p-6 shadow-soft">
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

                    @if ($order->isDeposit())
                        <div class="flex justify-between text-brand-800">
                            <dt>
                                عربون
                                @if ($order->depositApproved())
                                    مدفوع
                                @else
                                    قيد المراجعة
                                @endif
                            </dt>
                            <dd>{{ number_format($order->depositAmount(), 2) }} ج.م</dd>
                        </div>
                        @if (! $order->isVoided())
                            <div class="flex justify-between font-bold text-stone-900">
                                <dt>المتبقي عند الاستلام</dt>
                                <dd>{{ number_format($order->remainingAmount(), 2) }} ج.م</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                {{-- The same line the courier reads on the delivery sheet. --}}
                @if (! $order->isVoided())
                    <p class="mt-4 rounded-xl bg-stone-50 px-4 py-3 text-xs leading-relaxed text-stone-600">
                        {{ $order->deliveryNote() }}
                    </p>
                @endif
            </section>

            @if ($order->customer_note)
                <section class="rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-2 font-bold text-stone-900">ملاحظتك</h2>
                    <p class="text-sm text-stone-600">{{ $order->customer_note }}</p>
                </section>
            @endif
        </aside>
    </div>
@endsection
