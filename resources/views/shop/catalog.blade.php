@extends('layouts.shop')

@section('title', $heading)

@section('content')
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-stone-500">
        <a href="{{ route('home') }}" class="hover:text-amber-700">الرئيسية</a>
        @foreach ($ancestors as $ancestor)
            <span>/</span>
            <a href="{{ route('category', $ancestor) }}" class="hover:text-amber-700">{{ $ancestor->name }}</a>
        @endforeach
        <span>/</span>
        <span class="text-stone-900">{{ $heading }}</span>
    </nav>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">{{ $heading }}</h1>
            <p class="mt-1 text-sm text-stone-500">{{ $products->total() }} منتج</p>
        </div>

        <form action="{{ url()->current() }}" method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['sort', 'page']) as $key => $value)
                @foreach (is_array($value) ? $value : [$value] as $v)
                    <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                @endforeach
            @endforeach
            <label for="sort" class="text-sm text-stone-600">ترتيب:</label>
            <select name="sort" id="sort" onchange="this.form.submit()"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm outline-none focus:border-amber-600">
                <option value="newest" @selected(request('sort') === 'newest')>الأحدث</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>الأقل سعراً</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>الأعلى سعراً</option>
            </select>
        </form>
    </div>

    {{-- direct subcategories, so a branch page can be drilled into --}}
    @if ($category && $category->children->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($category->children->where('is_active', true) as $child)
                <a href="{{ route('category', $child) }}"
                   class="rounded-full border border-stone-300 bg-white px-3.5 py-1.5 text-sm text-stone-700
                          hover:border-amber-600 hover:text-amber-700">
                    {{ $child->name }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
        {{-- filters --}}
        <aside>
            <form action="{{ url()->current() }}" method="GET"
                  class="space-y-6 rounded-2xl border border-stone-200 bg-white p-5">
                @if (request('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif
                @if (request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif

                <div>
                    <h2 class="mb-3 text-sm font-semibold text-stone-900">السعر (ج.م)</h2>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min" min="0" value="{{ request('min') }}" placeholder="من"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-600">
                        <input type="number" name="max" min="0" value="{{ request('max') }}" placeholder="إلى"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-600">
                    </div>
                </div>

                @if ($brands->isNotEmpty())
                    <div>
                        <h2 class="mb-3 text-sm font-semibold text-stone-900">الماركة</h2>
                        <div class="max-h-56 space-y-2 overflow-y-auto pe-1">
                            @foreach ($brands as $brand)
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
                                    <input type="checkbox" name="brand[]" value="{{ $brand->id }}"
                                           @checked(in_array((string) $brand->id, (array) request('brand', []), true))
                                           class="size-4 rounded border-stone-300 text-amber-600 focus:ring-amber-600">
                                    {{ $brand->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-stone-700">
                    <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))
                           class="size-4 rounded border-stone-300 text-amber-600 focus:ring-amber-600">
                    المتاح فقط
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800">
                        تطبيق
                    </button>
                    <a href="{{ $category ? route('category', $category) : route('catalog') }}"
                       class="rounded-lg border border-stone-300 px-4 py-2.5 text-sm text-stone-700 hover:bg-stone-100">
                        مسح
                    </a>
                </div>
            </form>
        </aside>

        {{-- grid --}}
        <div>
            @if ($products->isEmpty())
                <x-empty title="لا توجد منتجات مطابقة"
                         description="جرّب توسيع نطاق السعر أو إزالة بعض الفلاتر."
                         :action="route('catalog')" action-label="تصفّح كل المنتجات" />
            @else
                <div class="grid grid-cols-2 gap-5 sm:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-10">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
@endsection
