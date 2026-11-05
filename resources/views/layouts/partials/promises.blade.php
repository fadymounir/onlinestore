{{--
    The four questions every Egyptian shopper asks before they trust a store
    they have not bought from before. Entirely static, so the AJAX shell never
    sends it: it is rendered once per full page load and left alone.
--}}

<section class="border-y border-stone-200/70 bg-white">
    <div class="mx-auto grid max-w-7xl grid-cols-2 gap-px bg-stone-200/70 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['الدفع عند الاستلام', 'ادفع لما توصلك الشحنة', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z'],
            ['شحن لكل المحافظات', 'مجاناً فوق 1500 ج.م', 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h.375c.621 0 1.125-.504 1.125-1.125V11.25c0-1.243-1.007-2.25-2.25-2.25H15M2.25 14.25v-3.75m0 0V5.625c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v8.625'],
            ['استبدال خلال 14 يوم', 'لو المنتج مش مظبوط', 'M16.023 9.348h4.992V4.356m-4.992 4.992-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7M4.031 9.865H.038m3.993 4.787H.038v4.992m3.993-4.992 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7'],
            ['دعم على واتساب', 'نرد عليك في دقائق', 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.556-4.03 8.25-9 8.25a9.8 9.8 0 0 1-2.555-.337A5.97 5.97 0 0 1 5.41 20.97a6 6 0 0 1-.474-.065 4.5 4.5 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z'],
        ] as [$title, $line, $icon])
            {{-- The text wraps rather than truncates: two columns on a 390px
                 phone cannot hold "استبدال خلال 14 يوم" on one line, and a
                 clipped promise is worse than a promise on two lines. --}}
            <div class="flex items-center gap-3 bg-white px-3 py-4 sm:px-4 sm:py-5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 sm:size-11">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold leading-snug text-stone-900">{{ $title }}</p>
                    <p class="text-xs leading-snug text-stone-500">{{ $line }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
