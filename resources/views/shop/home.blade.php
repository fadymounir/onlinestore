@extends($shopLayout ?? 'layouts.shop')

@section('title', 'الصفحة الرئيسية')

@php
    // The hero's collage is the catalogue itself rather than stock art: three
    // products that are already on the page below, so the photograph a shopper
    // taps at the top is a product they can actually buy.
    $heroProducts = $featured->merge($newest)
        ->filter(fn ($product) => $product->primaryImage)
        ->unique('id')
        ->take(3)
        ->values();
@endphp

@section('content')
    {{-- hero --}}
    <section class="hero-mesh relative overflow-hidden rounded-[1.75rem] px-5 py-9 sm:rounded-[2rem] sm:px-10 sm:py-16">
        <div class="relative grid items-center gap-10 lg:grid-cols-[1.05fr_1fr]">
            <div class="animate-rise">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3.5 py-1.5 text-xs
                             font-bold text-brand-800 shadow-soft backdrop-blur">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    تشكيلة جديدة كل أسبوع
                </span>

                <h1 class="mt-4 text-[28px] font-extrabold leading-[1.3] tracking-tight text-stone-900 sm:text-5xl">
                    {{ $storeTagline ?: 'تسوّق بسهولة من أي مكان في مصر' }}
                </h1>

                <p class="mt-3 max-w-lg text-sm leading-relaxed text-stone-600 sm:text-base">
                    إكسسوارات مختارة بعناية، بأسعار واضحة ومن غير مفاجآت. الدفع عند الاستلام
                    في كل المحافظات، والشحن مجاناً للطلبات فوق 1500 ج.م داخل القاهرة الكبرى.
                </p>

                <div class="mt-7 grid grid-cols-2 gap-2.5 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
                    <a href="{{ route('catalog') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-full bg-stone-900 px-5 py-3.5
                              text-sm font-bold text-white shadow-lift transition hover:bg-stone-800 sm:px-7">
                        تسوّق الآن
                        <svg class="size-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.4"
                             viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5 16.5 12 9 19.5"/>
                        </svg>
                    </a>
                    <a href="{{ route('catalog', ['sort' => 'newest']) }}"
                       class="rounded-full border border-stone-900/15 bg-white/70 px-5 py-3.5 text-center text-sm
                              font-bold text-stone-800 backdrop-blur transition hover:border-stone-900/30
                              hover:bg-white sm:px-7">
                        وصل حديثاً
                    </a>
                </div>
            </div>

            {{--
                The collage. On a phone it is a 3-up strip of portrait crops —
                a tall mosaic there pushes the first product shelf two screens
                down — and from `lg` it becomes one tall photograph beside two
                stacked ones.
            --}}
            @if ($heroProducts->isNotEmpty())
                <div class="mt-7 grid grid-cols-3 gap-2.5 lg:mt-0 lg:grid-cols-2 lg:gap-4">
                    <a href="{{ route('product', $heroProducts[0]) }}"
                       class="group relative overflow-hidden rounded-2xl bg-white shadow-lift lg:row-span-2 lg:rounded-3xl">
                        <img src="{{ Storage::disk('public')->url($heroProducts[0]->primaryImage->path) }}"
                             alt="{{ $heroProducts[0]->name }}"
                             class="aspect-[3/4] size-full object-cover transition duration-700
                                    group-hover:scale-105 lg:aspect-auto lg:min-h-80">
                        <span class="absolute inset-x-2 bottom-2 hidden truncate rounded-xl bg-white/90 px-3 py-2
                                     text-xs font-bold text-stone-900 backdrop-blur sm:block lg:inset-x-3
                                     lg:bottom-3 lg:rounded-2xl lg:px-4 lg:py-2.5">
                            {{ $heroProducts[0]->name }}
                        </span>
                    </a>

                    @foreach ($heroProducts->slice(1) as $product)
                        <a href="{{ route('product', $product) }}"
                           class="group relative overflow-hidden rounded-2xl bg-white shadow-soft lg:rounded-3xl">
                            <img src="{{ Storage::disk('public')->url($product->primaryImage->path) }}"
                                 alt="{{ $product->name }}"
                                 class="aspect-[3/4] size-full object-cover transition duration-700
                                        group-hover:scale-105 lg:aspect-square">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{--
        Categories. Six get a photograph each — the tiles are how a shopper who
        does not know what they want starts — and the rest stay as a chip row,
        since the header's menu already browses the tree in full depth.
    --}}
    @if ($categories->isNotEmpty())
        @php
            $tiles = $categories->take(6);
            $rest = $categories->slice(6);
        @endphp

        <section class="reveal mt-12 sm:mt-14">
            <div class="mb-5 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-stone-900 sm:text-2xl">تسوّق حسب القسم</h2>
                    <p class="mt-1 text-sm text-stone-500">{{ $categories->count() }} قسماً في المتجر</p>
                </div>
                <a href="{{ route('catalog') }}" class="shrink-0 text-sm font-semibold text-brand-700 transition hover:text-brand-800">
                    كل المنتجات <span aria-hidden="true">&larr;</span>
                </a>
            </div>

            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($tiles as $category)
                    <li>
                        <a href="{{ route('category', $category) }}"
                           class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-3xl
                                  bg-stone-200 shadow-soft transition hover:shadow-lift">
                            @if ($category->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image) }}"
                                     alt="" aria-hidden="true" loading="lazy"
                                     class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-110">
                            @endif
                            <span class="absolute inset-0 bg-gradient-to-t from-stone-950/80 via-stone-950/15 to-transparent"></span>
                            <span class="relative p-3 text-sm font-bold text-white">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($rest->isNotEmpty())
                <ul class="mt-4 flex flex-wrap gap-2">
                    @foreach ($rest as $category)
                        <li>
                            <a href="{{ route('category', $category) }}"
                               class="block rounded-full border border-stone-200 bg-white px-4 py-2 text-sm
                                      text-stone-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    {{-- Both shelves are the same grid, so they share one block of markup. On a
         phone they scroll sideways: a four-up grid turns into eight stacked
         cards and buries whatever follows. --}}
    @foreach ([
        ['منتجات مميّزة', 'اختيارات الفريق من التشكيلة', $featured, route('catalog')],
        ['وصل حديثاً', 'آخر ما أضفناه إلى المتجر', $newest, route('catalog', ['sort' => 'newest'])],
    ] as [$heading, $subheading, $products, $seeAll])
        @if ($products->isNotEmpty())
            <section class="reveal mt-12 sm:mt-14">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-extrabold tracking-tight text-stone-900 sm:text-2xl">{{ $heading }}</h2>
                        <p class="mt-1 text-sm text-stone-500">{{ $subheading }}</p>
                    </div>
                    <a href="{{ $seeAll }}"
                       class="shrink-0 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold
                              text-stone-700 transition hover:border-brand-300 hover:text-brand-800">
                        شاهد الكل
                    </a>
                </div>

                <div class="snap-row no-scrollbar -mx-4 px-4 pb-2 lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5
                            lg:overflow-visible lg:px-0">
                    @foreach ($products as $product)
                        <div class="snap-row-item w-[62%] sm:w-[40%] lg:w-auto">
                            <x-product-card :product="$product" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    {{-- closing band --}}
    <section class="reveal mt-14 overflow-hidden rounded-[1.75rem] bg-ink px-5 py-10 text-center sm:mt-16 sm:rounded-[2rem] sm:px-12 sm:py-12">
        <h2 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
            اطلب الآن، وادفع لما توصلك
        </h2>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-stone-400">
            الدفع عند الاستلام متاح في كل محافظات مصر، والاستبدال خلال 14 يوماً من الاستلام.
        </p>
        <a href="{{ route('catalog') }}"
           class="mt-7 inline-block rounded-full bg-white px-8 py-3.5 text-sm font-bold text-stone-900
                  transition hover:bg-brand-100">
            ابدأ التسوّق
        </a>
    </section>
@endsection
