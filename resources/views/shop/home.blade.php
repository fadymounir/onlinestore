@extends('layouts.shop')

@section('title', 'الصفحة الرئيسية')

@section('content')
    {{-- hero --}}
    <section class="mb-12 overflow-hidden rounded-3xl bg-stone-900 px-6 py-14 text-center sm:px-12 sm:py-20">
        <p class="mb-3 text-sm font-medium tracking-wide text-amber-400">أزياء لكل المناسبات</p>
        <h1 class="mx-auto max-w-2xl text-3xl font-bold leading-tight text-white sm:text-5xl">
            {{ $storeTagline ?: 'تسوّق بسهولة من أي مكان في مصر' }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-stone-300">
            الدفع عند الاستلام في كل المحافظات، والشحن مجاناً للطلبات فوق 1500 ج.م داخل القاهرة الكبرى.
        </p>
        <a href="{{ route('catalog') }}"
           class="mt-8 inline-block rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-900 transition hover:bg-amber-400">
            ابدأ التسوّق
        </a>
    </section>

    {{-- categories --}}
    @if ($categories->isNotEmpty())
        <section class="mb-14">
            <h2 class="mb-5 text-xl font-bold text-stone-900">تصفّح الأقسام</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <a href="{{ route('category', $category) }}"
                       class="group rounded-2xl border border-stone-200 bg-white p-5 text-center transition hover:border-amber-300 hover:bg-amber-50">
                        <span class="block text-sm font-semibold text-stone-900 group-hover:text-amber-800">
                            {{ $category->name }}
                        </span>
                        @if ($category->children->isNotEmpty())
                            <span class="mt-1 block text-[11px] text-stone-500">
                                {{ $category->children->count() }} قسم فرعي
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- featured --}}
    @if ($featured->isNotEmpty())
        <section class="mb-14">
            <div class="mb-5 flex items-end justify-between gap-4">
                <h2 class="text-xl font-bold text-stone-900">منتجات مميّزة</h2>
                <a href="{{ route('catalog') }}" class="text-sm font-medium text-amber-700 hover:underline">شاهد الكل</a>
            </div>
            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- newest --}}
    @if ($newest->isNotEmpty())
        <section>
            <div class="mb-5 flex items-end justify-between gap-4">
                <h2 class="text-xl font-bold text-stone-900">وصل حديثاً</h2>
                <a href="{{ route('catalog') }}?sort=newest" class="text-sm font-medium text-amber-700 hover:underline">شاهد الكل</a>
            </div>
            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                @foreach ($newest as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
