{{--
    The whole cart body: lines, summary and coupon box.

    Split out of cart.blade.php so CartController can re-render exactly this
    much after an AJAX update and hand it back as the swap payload — the totals,
    the discount row and the coupon box all move when a quantity changes, so
    patching them in JavaScript would mean reimplementing the money formatting
    and the coupon rules on the client.

    @param \Illuminate\Support\Collection $lines
    @param float $subtotal
    @param \App\Models\Coupon|null $coupon
    @param string|null $couponCode
--}}
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

                <div class="flex gap-3 rounded-3xl bg-white p-3 shadow-soft sm:gap-4 sm:p-4">
                    <a href="{{ route('product', $product) }}" class="shrink-0">
                        @if ($image = $variant->image ?? $product->primaryImage?->path)
                            <img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $product->name }}"
                                 class="size-24 rounded-2xl object-cover sm:size-28">
                        @else
                            <span class="flex size-24 items-center justify-center rounded-2xl bg-stone-100 text-xs text-stone-400 sm:size-28">بلا صورة</span>
                        @endif
                    </a>

                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-start gap-2">
                            <h2 class="flex-1 text-sm font-bold leading-snug text-stone-900">
                                <a href="{{ route('product', $product) }}" class="transition hover:text-brand-700">{{ $product->name }}</a>
                            </h2>

                            <form action="{{ route('cart.remove', $variant) }}" method="POST" data-ajax>
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="إزالة" aria-label="إزالة المنتج"
                                        class="-m-1 flex size-9 items-center justify-center rounded-full text-stone-400
                                               transition hover:bg-red-50 hover:text-red-600">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.67.1 1.01.16m-1 0L19.23 19.3a2.25 2.25 0 0 1-2.24 2.08H7.01a2.25 2.25 0 0 1-2.24-2.08L3.76 5.93m15.47 0a48 48 0 0 0-3.48-.4m-9.99.4c.34-.06.67-.11 1.01-.16m8.98.56V4.48c0-1.14-.88-2.1-2.02-2.13a52 52 0 0 0-3.48 0c-1.14.03-2.02 1-2.02 2.13v1.3m7.52 0a48 48 0 0 0-7.52 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                        @if ($label = $variant->label())
                            <p class="mt-0.5 text-xs text-stone-500">{{ $label }}</p>
                        @endif

                        @if ($variant->stock_quantity < $line['quantity'])
                            <p class="mt-1.5 text-xs font-semibold text-red-700">
                                المتاح الآن {{ $variant->stock_quantity }} فقط — عدّل الكمية.
                            </p>
                        @endif

                        <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-3">
                            {{--
                                A stepper rather than a number field: on a phone
                                a spinner's arrows are a 10px target and the
                                keyboard it opens covers the cart. Each button is
                                its own form posting the new quantity, so it
                                works with JavaScript off as well — and with it
                                on, `data-ajax` swaps the panel in place.
                            --}}
                            <div class="flex items-center gap-1 rounded-full bg-stone-100 p-1">
                                @php $max = max(1, min(99, $variant->stock_quantity)); @endphp

                                <form action="{{ route('cart.update', $variant) }}" method="POST" data-ajax>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="quantity" value="{{ max(1, $line['quantity'] - 1) }}">
                                    <button type="submit" aria-label="أنقص الكمية" @disabled($line['quantity'] <= 1)
                                            class="flex size-9 items-center justify-center rounded-full bg-white text-stone-700
                                                   shadow-soft transition hover:text-brand-700 disabled:opacity-40">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" d="M5 12h14"/>
                                        </svg>
                                    </button>
                                </form>

                                <span class="min-w-8 text-center text-sm font-bold tabular-nums text-stone-900">{{ $line['quantity'] }}</span>

                                <form action="{{ route('cart.update', $variant) }}" method="POST" data-ajax>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="quantity" value="{{ min($max, $line['quantity'] + 1) }}">
                                    <button type="submit" aria-label="زد الكمية" @disabled($line['quantity'] >= $max)
                                            class="flex size-9 items-center justify-center rounded-full bg-white text-stone-700
                                                   shadow-soft transition hover:text-brand-700 disabled:opacity-40">
                                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            <div class="text-end">
                                <x-price :amount="(float) $variant->price * $line['quantity']" size="sm" />
                                <p class="mt-0.5 text-xs text-stone-400">{{ number_format((float) $variant->price, 2) }} × {{ $line['quantity'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- summary --}}
        <aside class="space-y-4">
            <div class="rounded-3xl bg-white p-6 shadow-soft">
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
                    {{-- Deliberately a real navigation: placing an order is not a background task. --}}
                    <a href="{{ route('checkout') }}"
                       class="mt-5 block rounded-xl bg-stone-900 px-6 py-3 text-center font-bold text-white transition hover:bg-stone-800">
                        إتمام الطلب
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="mt-5 block rounded-xl bg-stone-900 px-6 py-3 text-center font-bold text-white transition hover:bg-stone-800">
                        سجّل الدخول لإتمام الطلب
                    </a>
                    <p class="mt-2 text-center text-xs text-stone-500">سلتك محفوظة وستنتقل معك بعد الدخول.</p>
                @endauth

                <a href="{{ route('catalog') }}" class="mt-3 block text-center text-sm text-stone-600 hover:text-brand-700">
                    متابعة التسوّق
                </a>
            </div>

            {{-- coupon --}}
            <div class="rounded-3xl bg-white p-6 shadow-soft">
                <h2 class="mb-3 text-sm font-bold text-stone-900">كوبون الخصم</h2>

                @if ($coupon)
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-3 py-2.5">
                        <span class="text-sm font-semibold text-emerald-800" dir="ltr">{{ $coupon->code }}</span>
                        <form action="{{ route('cart.coupon.remove') }}" method="POST" data-ajax>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-700 hover:underline">إلغاء</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('cart.coupon.apply') }}" method="POST" data-ajax class="flex gap-2">
                        @csrf
                        <input type="text" name="code" value="{{ $couponCode }}" placeholder="أدخل الكود" dir="ltr"
                               class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                        <button type="submit" class="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800">
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

    {{--
        The phone's checkout bar.

        It lives inside the swapped region on purpose: every cart mutation
        re-renders this partial, so the total here can never drift from the
        summary above. It is opaque at z-40 rather than stacked above the
        bottom tab bar — it covers it, so the thumb zone holds one control
        instead of two — and because it belongs to the panel, removing the
        last line takes it away and hands the tabs back.
    --}}
    <div class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-3 border-t border-stone-200 bg-white px-4
                py-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:hidden">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold text-stone-500">الإجمالي</p>
            <p class="whitespace-nowrap text-base font-extrabold tabular-nums text-stone-900">
                {{ number_format($subtotal - $discount, 2) }}
                <span class="text-xs font-medium text-stone-400">ج.م</span>
            </p>
        </div>

        @auth
            <a href="{{ route('checkout') }}"
               class="flex-1 rounded-xl bg-stone-900 px-5 py-3.5 text-center font-bold text-white transition hover:bg-stone-800">
                إتمام الطلب
            </a>
        @else
            <a href="{{ route('login') }}"
               class="flex-1 rounded-xl bg-stone-900 px-5 py-3.5 text-center text-sm font-bold text-white transition hover:bg-stone-800">
                سجّل الدخول لإتمام الطلب
            </a>
        @endauth
    </div>
@endif
