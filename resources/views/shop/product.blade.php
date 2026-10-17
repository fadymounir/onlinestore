@extends('layouts.shop')

@section('title', $product->name)
@section('description', $product->short_description)

@php
    use App\Enums\AttributeType;

    $reviews = $product->approvedReviews;
    $avgRating = $reviews->avg('rating') ?? 0;
    $gallery = $product->images->sortByDesc('is_primary');
    $hero = $selected?->image ?? $gallery->first()?->path;

    // A value is reachable if some active, in-stock variant pairs it with
    // everything already chosen from the OTHER attributes.
    $isAvailable = function ($valueId, $attributeId) use ($product, $chosen) {
        $others = $product->attributes
            ->where('id', '!=', $attributeId)
            ->pluck('values')
            ->flatten()
            ->pluck('id');

        $keep = $chosen->intersect($others);

        return $product->variants->contains(function ($variant) use ($valueId, $keep) {
            if (! $variant->is_active || $variant->stock_quantity < 1) {
                return false;
            }

            $ids = $variant->attributeValues->pluck('id');

            return $ids->contains($valueId) && $keep->diff($ids)->isEmpty();
        });
    };

    // Toggling a value keeps the other attributes' choices in the URL.
    $linkFor = function ($valueId, $attributeId) use ($product, $chosen) {
        $sameAttribute = $product->attributes->firstWhere('id', $attributeId)->values->pluck('id');

        $next = $chosen->reject(fn ($id) => $sameAttribute->contains($id));

        if (! $chosen->contains($valueId)) {
            $next = $next->push($valueId);
        }

        return route('product', $product) . ($next->isEmpty() ? '' : '?' . http_build_query(['v' => $next->values()->all()]));
    };
@endphp

@section('content')
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-stone-500">
        <a href="{{ route('home') }}" class="hover:text-amber-700">الرئيسية</a>
        @if ($product->category?->parent)
            <span>/</span>
            <a href="{{ route('category', $product->category->parent) }}" class="hover:text-amber-700">{{ $product->category->parent->name }}</a>
        @endif
        @if ($product->category)
            <span>/</span>
            <a href="{{ route('category', $product->category) }}" class="hover:text-amber-700">{{ $product->category->name }}</a>
        @endif
    </nav>

    <div class="grid gap-10 lg:grid-cols-2">
        {{-- gallery --}}
        <div>
            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-stone-100">
                @if ($hero)
                    <img src="{{ Storage::disk('public')->url($hero) }}" alt="{{ $product->name }}"
                         class="aspect-[3/4] w-full object-cover">
                @else
                    <div class="flex aspect-[3/4] items-center justify-center text-stone-400">بلا صورة</div>
                @endif
            </div>

            @if ($gallery->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-3">
                    @foreach ($gallery as $image)
                        <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?? $product->name }}"
                             class="aspect-square w-full cursor-zoom-in rounded-lg border border-stone-200 object-cover hover:border-amber-500">
                    @endforeach
                </div>
            @endif
        </div>

        {{-- buy box --}}
        <div>
            @if ($product->brand)
                <p class="mb-1 text-sm font-medium text-stone-500">{{ $product->brand->name }}</p>
            @endif

            <h1 class="text-2xl font-bold leading-snug text-stone-900 sm:text-3xl">{{ $product->name }}</h1>

            @if ($reviews->isNotEmpty())
                <a href="#reviews" class="mt-2 inline-block">
                    <x-stars :rating="$avgRating" :count="$reviews->count()" />
                </a>
            @endif

            <div class="mt-4">
                <x-price :amount="$selected?->price ?? $product->base_price"
                         :compare="$selected?->compare_at_price ?? $product->compare_at_price"
                         size="lg" />
            </div>

            @if ($product->short_description)
                <p class="mt-4 leading-relaxed text-stone-600">{{ $product->short_description }}</p>
            @endif

            {{-- variant pickers --}}
            @foreach ($product->attributes->sortBy('pivot.sort_order') as $attribute)
                <div class="mt-6">
                    <h2 class="mb-2 text-sm font-semibold text-stone-900">
                        {{ $attribute->name }}
                        @php $picked = $attribute->values->firstWhere(fn ($v) => $chosen->contains($v->id)); @endphp
                        @if ($picked)
                            <span class="font-normal text-stone-500">— {{ $picked->value }}</span>
                        @endif
                    </h2>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($attribute->values as $value)
                            @php
                                $active = $chosen->contains($value->id);
                                $available = $isAvailable($value->id, $attribute->id);
                            @endphp

                            <a href="{{ $linkFor($value->id, $attribute->id) }}"
                               @class([
                                   'relative flex items-center justify-center rounded-lg border text-sm transition',
                                   $attribute->type === AttributeType::Color ? 'size-10' : 'min-w-12 px-3 py-2',
                                   'border-amber-600 ring-2 ring-amber-600/30' => $active,
                                   'border-stone-300 hover:border-stone-500' => ! $active,
                                   'opacity-40' => ! $available,
                               ])
                               title="{{ $value->value }}{{ $available ? '' : ' — غير متاح' }}">
                                @if ($attribute->type === AttributeType::Color && $value->color_hex)
                                    <span class="size-6 rounded-full border border-stone-300"
                                          style="background-color: {{ $value->color_hex }}"></span>
                                    <span class="sr-only">{{ $value->value }}</span>
                                @else
                                    {{ $value->value }}
                                @endif

                                @unless ($available)
                                    <span class="absolute inset-0 flex items-center justify-center">
                                        <span class="h-px w-full rotate-45 bg-stone-400"></span>
                                    </span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- add to cart --}}
            <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-5">
                @if ($product->attributes->isNotEmpty() && $chosen->count() < $product->attributes->count())
                    <p class="text-sm text-stone-600">اختر {{ $product->attributes->pluck('name')->implode(' و') }} لإضافة المنتج إلى السلة.</p>
                @elseif (! $selected)
                    <p class="text-sm font-medium text-red-700">هذه التركيبة غير متوفّرة. اختر تركيبة أخرى.</p>
                @elseif ($selected->stock_quantity < 1)
                    <p class="text-sm font-medium text-red-700">نفدت الكمية من هذه التركيبة.</p>
                @else
                    @if ($selected->isLowStock())
                        <p class="mb-3 text-sm font-medium text-amber-700">
                            باقي {{ $selected->stock_quantity }} قطعة فقط — اطلبها قبل أن تنفد.
                        </p>
                    @endif

                    <form action="{{ route('cart.add', $selected) }}" method="POST" data-ajax
                          class="flex flex-wrap items-center gap-3">
                        @csrf
                        <label for="quantity" class="text-sm text-stone-600">الكمية</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1"
                               max="{{ min(99, $selected->stock_quantity) }}"
                               class="w-20 rounded-lg border border-stone-300 px-3 py-2.5 text-center outline-none focus:border-amber-600">

                        <button type="submit"
                                class="flex-1 rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white transition hover:bg-amber-700">
                            أضف إلى السلة
                        </button>
                    </form>

                    <p class="mt-3 text-xs text-stone-500">
                        SKU: <span dir="ltr">{{ $selected->sku }}</span> · الدفع عند الاستلام متاح
                    </p>
                @endif

                @auth
                    <form action="{{ route('wishlist.toggle', $product) }}" method="POST" data-ajax class="mt-4">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-stone-600 hover:text-red-600">
                            ♡ أضف إلى المفضّلة
                        </button>
                    </form>
                @endauth
            </div>

            @if ($product->description)
                <div class="mt-8">
                    <h2 class="mb-3 text-lg font-bold text-stone-900">تفاصيل المنتج</h2>
                    <div class="prose-sm max-w-none leading-relaxed text-stone-700 [&_li]:my-1 [&_ul]:list-inside [&_ul]:list-disc">
                        {!! \App\Support\Html::clean($product->description) !!}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- reviews --}}
    <section id="reviews" class="mt-16 border-t border-stone-200 pt-10">
        <h2 class="mb-6 text-xl font-bold text-stone-900">
            تقييمات العملاء
            @if ($reviews->isNotEmpty())
                <span class="ms-2 align-middle text-base font-normal text-stone-500">
                    <x-stars :rating="$avgRating" :count="$reviews->count()" />
                </span>
            @endif
        </h2>

        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                @forelse ($reviews as $review)
                    <article class="rounded-2xl border border-stone-200 bg-white p-5">
                        <div class="mb-2 flex flex-wrap items-center gap-3">
                            <x-stars :rating="$review->rating" />
                            <span class="text-sm font-semibold text-stone-900">{{ $review->user->name }}</span>
                            @if ($review->isVerifiedPurchase())
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                    شراء موثّق
                                </span>
                            @endif
                            <span class="text-xs text-stone-400">{{ $review->created_at->translatedFormat('j F Y') }}</span>
                        </div>
                        @if ($review->title)
                            <h3 class="font-semibold text-stone-900">{{ $review->title }}</h3>
                        @endif
                        @if ($review->comment)
                            <p class="mt-1 leading-relaxed text-stone-700">{{ $review->comment }}</p>
                        @endif
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-stone-300 bg-white px-5 py-10 text-center text-sm text-stone-600">
                        لا تقييمات بعد — كن أول من يقيّم هذا المنتج.
                    </p>
                @endforelse
            </div>

            <div>
                @auth
                    <form action="{{ route('review.store', $product) }}" method="POST"
                          class="rounded-2xl border border-stone-200 bg-white p-5">
                        @csrf
                        <h3 class="mb-4 font-bold text-stone-900">اكتب تقييمك</h3>

                        <label class="mb-1 block text-sm text-stone-600">التقييم</label>
                        <select name="rating" required
                                class="mb-4 w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                            <option value="">اختر عدد النجوم</option>
                            <option value="5">★★★★★ ممتاز</option>
                            <option value="4">★★★★ جيد جداً</option>
                            <option value="3">★★★ مقبول</option>
                            <option value="2">★★ ضعيف</option>
                            <option value="1">★ سيئ</option>
                        </select>

                        <label class="mb-1 block text-sm text-stone-600">العنوان</label>
                        <input type="text" name="title" maxlength="255"
                               class="mb-4 w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">

                        <label class="mb-1 block text-sm text-stone-600">رأيك في المنتج</label>
                        <textarea name="comment" rows="4" maxlength="2000"
                                  class="mb-4 w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600"></textarea>

                        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-semibold text-white hover:bg-stone-800">
                            أرسل التقييم
                        </button>
                        <p class="mt-2 text-xs text-stone-500">يظهر التقييم بعد مراجعته.</p>
                    </form>
                @else
                    <div class="rounded-2xl border border-stone-200 bg-white p-5 text-center">
                        <p class="text-sm text-stone-600">سجّل الدخول لتتمكن من تقييم المنتج.</p>
                        <a href="{{ route('login') }}"
                           class="mt-4 inline-block rounded-full bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            تسجيل الدخول
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-16">
            <h2 class="mb-5 text-xl font-bold text-stone-900">قد يعجبك أيضاً</h2>
            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                @foreach ($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
