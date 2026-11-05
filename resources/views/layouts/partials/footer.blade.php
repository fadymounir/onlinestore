{{--
    The footer. Rendered from layouts/shop.blade.php and, when the signed-in
    identity changes mid-session, from layouts/shop-shell.blade.php — the
    account column is the half of it that depends on who is looking.
--}}

    {{-- The information column only exists when an admin has marked a page for
         the footer, so the track count follows it rather than leaving a gap. --}}
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 text-center sm:grid-cols-2 sm:text-start {{ $footerPages->isNotEmpty() ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
        <div class="sm:col-span-2 lg:col-span-1">
            <div class="mb-4 flex items-center justify-center gap-2.5 sm:justify-start">
                <img src="{{ asset('images/mark.svg') }}" alt="" width="64" height="64" class="size-10 rounded-xl">
                <h2 class="text-lg font-extrabold tracking-tight text-white">{{ $storeName }}</h2>
            </div>
            {{-- mx-auto so the capped width centres under the wordmark
                 instead of hugging the start edge of a centred column. --}}
            <p class="mx-auto max-w-sm text-sm leading-relaxed sm:mx-0">{{ $storeTagline }}</p>
            <x-social-links :socials="$socials" :whatsapp-url="$storeWhatsappUrl"
                            class="mt-5 justify-center sm:justify-start" />
        </div>
        <div>
            <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-white">التصنيفات</h3>
            <ul class="space-y-2.5 text-sm">
                @foreach ($navCategories->take(5) as $cat)
                    <li><a href="{{ route('category', $cat) }}" class="transition hover:text-brand-300">{{ $cat->name }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-white">حسابي</h3>
            <ul class="space-y-2.5 text-sm">
                @auth
                    <li><a href="{{ route('account.orders.index') }}" class="transition hover:text-brand-300">طلباتي</a></li>
                    <li><a href="{{ route('account.addresses.index') }}" class="transition hover:text-brand-300">عناويني</a></li>
                    <li><a href="{{ route('wishlist') }}" class="transition hover:text-brand-300">المفضّلة</a></li>
                @else
                    <li><a href="{{ route('login') }}" class="transition hover:text-brand-300">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="transition hover:text-brand-300">إنشاء حساب</a></li>
                @endauth
                <li><a href="{{ route('cart.index') }}" class="transition hover:text-brand-300">سلة الشراء</a></li>
            </ul>
        </div>
        @if ($footerPages->isNotEmpty())
            <div>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-white">معلومات</h3>
                <ul class="space-y-2.5 text-sm">
                    @foreach ($footerPages as $footerPage)
                        <li>
                            <a href="{{ route('page', $footerPage) }}" class="transition hover:text-brand-300">
                                {{ $footerPage->menuLabel() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-white">تواصل معنا</h3>
            <ul class="space-y-2.5 text-sm">
                @if ($storePhone)<li>هاتف: <a href="tel:{{ $storePhone }}" dir="ltr" class="transition hover:text-brand-300">{{ $storePhone }}</a></li>@endif
                @if ($storeWhatsapp)
                    <li>
                        واتساب:
                        <a href="{{ $storeWhatsappUrl }}" target="_blank" rel="noopener noreferrer"
                           dir="ltr" class="transition hover:text-brand-300">{{ $storeWhatsapp }}</a>
                    </li>
                @endif
                @if ($storeEmail)<li><a href="mailto:{{ $storeEmail }}" class="transition hover:text-brand-300">{{ $storeEmail }}</a></li>@endif
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-xs sm:flex-row">
            <p>جميع الحقوق محفوظة &copy; {{ now()->year }} {{ $storeName }}</p>
            <p class="flex items-center gap-2">
                <span class="rounded-full border border-white/15 px-3 py-1">الدفع عند الاستلام</span>
                <span class="rounded-full border border-white/15 px-3 py-1">شحن لكل المحافظات</span>
            </p>
        </div>
    </div>
