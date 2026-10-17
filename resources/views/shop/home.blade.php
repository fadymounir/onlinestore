@extends('layouts.shop')

@section('title', 'الصفحة الرئيسية')

@section('content')
    {{--
        A plain masthead rather than a hero panel. On a shop the products are
        the page, and a full-bleed dark block with its own type scale mostly
        pushes them below the fold.
    --}}
    <section class="mb-10 border-b border-stone-200 pb-8">
        <h1 class="max-w-2xl text-2xl font-bold leading-snug text-stone-900 sm:text-3xl">
            {{ $storeTagline ?: 'تسوّق بسهولة من أي مكان في مصر' }}
        </h1>
        <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">
            الدفع عند الاستلام في كل المحافظات، والشحن مجاناً للطلبات فوق 1500 ج.م
            داخل القاهرة الكبرى.
        </p>
        <a href="{{ route('catalog') }}"
           class="mt-5 inline-block rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800">
            تصفّح كل المنتجات
        </a>
    </section>

    {{--
        Categories as plain links. Twenty-one photo tiles were the bulk of this
        page, and the mega menu in the header already browses the tree in full
        depth — this is just a flat shortcut to each root.
    --}}
    @if ($categories->isNotEmpty())
        <section class="mb-12">
            <h2 class="mb-4 text-base font-bold text-stone-900">الأقسام</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('category', $category) }}"
                           class="block rounded-lg border border-stone-200 bg-white px-3.5 py-2 text-sm text-stone-700 transition hover:border-stone-400 hover:text-stone-900">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Both shelves are the same grid, so they share one block of markup. --}}
    @foreach ([
        ['منتجات مميّزة', $featured, route('catalog')],
        ['وصل حديثاً', $newest, route('catalog', ['sort' => 'newest'])],
    ] as [$heading, $products, $seeAll])
        @if ($products->isNotEmpty())
            <section class="mb-12">
                <div class="mb-4 flex items-baseline justify-between gap-4">
                    <h2 class="text-base font-bold text-stone-900">{{ $heading }}</h2>
                    <a href="{{ $seeAll }}" class="text-sm text-stone-500 transition hover:text-stone-900">
                        شاهد الكل
                    </a>
                </div>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
@endsection
