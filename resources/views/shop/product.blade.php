@extends($shopLayout ?? 'layouts.shop')

@section('title', $product->name)
{{-- Guarded, not defaulted: @section with a null body is how Blade is told
     the content follows as a block, so it opens an output buffer and waits for
     an @endsection that never comes. Skipping it also lets the layout fall
     back to the store's own tagline, which beats an empty meta tag. --}}
@if ($product->short_description)
    @section('description', $product->short_description)
@endif

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

{{--
    The phone's buy bar. It replaces the bottom tab bar on this page (see
    layouts/shop.blade.php): the gallery fills the first screen, so without it
    the price and the button are both below the fold on every product.
    Rendered only when there is something to add — an unchosen or sold-out
    variant gets the tabs back instead.
--}}
@if ($selected && $selected->stock_quantity > 0 && ($product->attributes->isEmpty() || $chosen->count() >= $product->attributes->count()))
    @section('mobile-bar')
        <div data-buybar
             class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t border-stone-200 bg-white/95
                    px-4 py-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] backdrop-blur-xl md:hidden">
            <div class="min-w-0">
                <x-price :amount="$selected->price" :compare="$selected->compare_at_price" size="sm" />
            </div>

            <form action="{{ route('cart.add', $selected) }}" method="POST" data-ajax class="flex-1">
                @csrf
                <input type="hidden" name="quantity" value="1">
                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-stone-900 px-5 py-3.5
                               font-bold text-white transition hover:bg-stone-800">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4a1.5 1.5 0 0 1 1.47 1.2L5.4 6m0 0 1.8 8.5a1.5 1.5 0 0 0 1.47 1.2h8.2a1.5 1.5 0 0 0 1.46-1.14l1.6-6.4A.9.9 0 0 0 19.06 6H5.4Z"/>
                    </svg>
                    أضف إلى السلة
                </button>
            </form>
        </div>
    @endsection
@endif

@section('content')
    <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-sm text-stone-500" aria-label="مسار التصفّح">
        <a href="{{ route('home') }}" class="transition hover:text-brand-700">الرئيسية</a>
        @if ($product->category?->parent)
            <span aria-hidden="true" class="text-stone-300">/</span>
            <a href="{{ route('category', $product->category->parent) }}" class="transition hover:text-brand-700">{{ $product->category->parent->name }}</a>
        @endif
        @if ($product->category)
            <span aria-hidden="true" class="text-stone-300">/</span>
            <a href="{{ route('category', $product->category) }}" class="transition hover:text-brand-700">{{ $product->category->name }}</a>
        @endif
    </nav>

    <div class="grid gap-10 lg:grid-cols-2 lg:items-start">
        {{--
            Gallery. The thumbnails are links to the full-size file, so with
            JavaScript off they still open the photograph; app.js intercepts
            them and swaps the main image in place instead.
        --}}
        <div data-gallery class="lg:sticky lg:top-36">
            <div class="overflow-hidden rounded-3xl bg-white shadow-soft">
                @if ($hero)
                    <img src="{{ Storage::disk('public')->url($hero) }}" alt="{{ $product->name }}"
                         data-gallery-main class="aspect-[4/5] w-full object-cover sm:aspect-[3/4]">
                @else
                    <div class="flex aspect-[3/4] items-center justify-center text-stone-400">بلا صورة</div>
                @endif
            </div>

            @if ($gallery->count() > 1)
                <div class="snap-row no-scrollbar mt-3 gap-3">
                    @foreach ($gallery as $image)
                        <a href="{{ Storage::disk('public')->url($image->path) }}"
                           class="snap-row-item w-16 overflow-hidden sm:w-20 rounded-2xl bg-white p-1 shadow-soft
                                  ring-2 ring-transparent transition hover:ring-brand-300
                                  aria-[current=true]:ring-brand-500"
                           @if ($image->path === $hero) aria-current="true" @endif>
                            <img src="{{ Storage::disk('public')->url($image->path) }}"
                                 alt="{{ $image->alt ?? $product->name }}" loading="lazy"
                                 class="aspect-square w-full rounded-xl object-cover">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- buy box --}}
        <div>
            @if ($product->brand)
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-brand-700">{{ $product->brand->name }}</p>
            @endif

            <h1 class="text-2xl font-extrabold leading-snug tracking-tight text-stone-900 sm:text-4xl">{{ $product->name }}</h1>

            @if ($reviews->isNotEmpty())
                <a href="#reviews" class="mt-3 inline-block">
                    <x-stars :rating="$avgRating" :count="$reviews->count()" />
                </a>
            @endif

            <div class="mt-5">
                <x-price :amount="$selected?->price ?? $product->base_price"
                         :compare="$selected?->compare_at_price ?? $product->compare_at_price"
                         size="lg" />
            </div>

            @if ($product->short_description)
                <p class="mt-4 leading-relaxed text-stone-600">{{ $product->short_description }}</p>
            @endif

            {{-- variant pickers --}}
            @foreach ($product->attributes->sortBy('pivot.sort_order') as $attribute)
                <div class="mt-7">
                    <h2 class="mb-2.5 text-sm font-bold text-stone-900">
                        {{ $attribute->name }}
                        @php $picked = $attribute->values->firstWhere(fn ($v) => $chosen->contains($v->id)); @endphp
                        @if ($picked)
                            <span class="font-medium text-stone-500">— {{ $picked->value }}</span>
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
                                   'relative flex items-center justify-center rounded-xl text-sm font-semibold transition',
                                   $attribute->type === AttributeType::Color ? 'size-11 p-1' : 'min-w-14 px-4 py-2.5',
                                   'bg-stone-900 text-white shadow-soft' => $active && $attribute->type !== AttributeType::Color,
                                   'ring-2 ring-stone-900 ring-offset-2' => $active && $attribute->type === AttributeType::Color,
                                   'bg-white text-stone-700 shadow-soft hover:text-brand-800' => ! $active,
                                   'opacity-40' => ! $available,
                               ])
                               title="{{ $value->value }}{{ $available ? '' : ' — غير متاح' }}">
                                @if ($attribute->type === AttributeType::Color && $value->color_hex)
                                    <span class="size-full rounded-lg border border-stone-200"
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
            <div class="mt-8 rounded-3xl bg-white p-6 shadow-soft">
                @if ($product->attributes->isNotEmpty() && $chosen->count() < $product->attributes->count())
                    <p class="text-sm text-stone-600">اختر {{ $product->attributes->pluck('name')->implode(' و') }} لإضافة المنتج إلى السلة.</p>
                @elseif (! $selected)
                    <p class="text-sm font-semibold text-red-700">هذه التركيبة غير متوفّرة. اختر تركيبة أخرى.</p>
                @elseif ($selected->stock_quantity < 1)
                    <p class="text-sm font-semibold text-red-700">نفدت الكمية من هذه التركيبة.</p>
                @else
                    @if ($selected->isLowStock())
                        <p class="mb-4 flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-800">
                            <span class="size-1.5 shrink-0 rounded-full bg-brand-500"></span>
                            باقي {{ $selected->stock_quantity }} قطعة فقط — اطلبها قبل أن تنفد.
                        </p>
                    @endif

                    {{-- data-buybar-anchor: while this is on screen the phone's
                         fixed bar stays out of the way — see resources/js/app.js. --}}
                    <form action="{{ route('cart.add', $selected) }}" method="POST" data-ajax data-buybar-anchor
                          class="flex flex-wrap items-center gap-3">
                        @csrf
                        <label for="quantity" class="sr-only">الكمية</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1"
                               max="{{ min(99, $selected->stock_quantity) }}"
                               class="w-20 rounded-xl border border-stone-200 bg-stone-50 px-3 py-3.5 text-center
                                      font-semibold outline-none transition focus:border-brand-400 focus:bg-white
                                      focus:ring-4 focus:ring-brand-500/10">

                        <button type="submit"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-stone-900 px-6 py-3.5
                                       font-bold text-white shadow-lift transition hover:bg-stone-800">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4a1.5 1.5 0 0 1 1.47 1.2L5.4 6m0 0 1.8 8.5a1.5 1.5 0 0 0 1.47 1.2h8.2a1.5 1.5 0 0 0 1.46-1.14l1.6-6.4A.9.9 0 0 0 19.06 6H5.4Z"/>
                            </svg>
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
                        <button type="submit" class="flex items-center gap-2 text-sm font-semibold text-stone-600 transition hover:text-red-600">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3-2.5 0-4.5 2-4.5 4.5 0 6 8.5 10.5 8.5 10.5S21 14.25 21 8.25Z"/>
                            </svg>
                            أضف إلى المفضّلة
                        </button>
                    </form>
                @endauth

                <ul class="mt-5 grid gap-2.5 border-t border-stone-100 pt-5 text-sm text-stone-600">
                    @foreach (['الدفع عند الاستلام في كل المحافظات', 'شحن خلال 2–5 أيام عمل', 'استبدال خلال 14 يوماً من الاستلام'] as $promise)
                        <li class="flex items-center gap-2.5">
                            <svg class="size-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5"
                                 viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            {{ $promise }}
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($product->description)
                <div class="mt-8 rounded-3xl bg-white p-6 shadow-soft">
                    <h2 class="mb-3 text-lg font-bold text-stone-900">تفاصيل المنتج</h2>
                    <div class="prose-shop text-[15px]">
                        {!! \App\Support\Html::clean($product->description) !!}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- reviews --}}
    <section id="reviews" class="mt-16 border-t border-stone-200 pt-12">
        <h2 class="mb-6 flex flex-wrap items-center gap-3 text-xl font-extrabold tracking-tight text-stone-900 sm:text-2xl">
            تقييمات العملاء
            @if ($reviews->isNotEmpty())
                <span class="text-base font-normal text-stone-500">
                    <x-stars :rating="$avgRating" :count="$reviews->count()" />
                </span>
            @endif
        </h2>

        <div class="grid gap-8 lg:grid-cols-[1fr_380px]">
            <div class="space-y-4">
                @forelse ($reviews as $review)
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <div class="mb-2 flex flex-wrap items-center gap-3">
                            <x-stars :rating="$review->rating" />
                            <span class="text-sm font-bold text-stone-900">{{ $review->user->name }}</span>
                            @if ($review->isVerifiedPurchase())
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                    شراء موثّق
                                </span>
                            @endif
                            <span class="text-xs text-stone-400">{{ $review->created_at->translatedFormat('j F Y') }}</span>
                        </div>
                        @if ($review->title)
                            <h3 class="font-bold text-stone-900">{{ $review->title }}</h3>
                        @endif
                        @if ($review->comment)
                            <p class="mt-1 leading-relaxed text-stone-600">{{ $review->comment }}</p>
                        @endif
                    </article>
                @empty
                    <p class="rounded-3xl border border-dashed border-stone-300 bg-white px-5 py-12 text-center text-sm text-stone-500">
                        لا تقييمات بعد — كن أول من يقيّم هذا المنتج.
                    </p>
                @endforelse
            </div>

            <div>
                @auth
                    <form action="{{ route('review.store', $product) }}" method="POST"
                          class="rounded-3xl bg-white p-6 shadow-soft">
                        @csrf
                        <h3 class="mb-4 font-bold text-stone-900">اكتب تقييمك</h3>

                        <label class="mb-1.5 block text-sm text-stone-600">التقييم</label>
                        <select name="rating" required
                                class="mb-4 w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-sm
                                       outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                            <option value="">اختر عدد النجوم</option>
                            <option value="5">★★★★★ ممتاز</option>
                            <option value="4">★★★★ جيد جداً</option>
                            <option value="3">★★★ مقبول</option>
                            <option value="2">★★ ضعيف</option>
                            <option value="1">★ سيئ</option>
                        </select>

                        <label class="mb-1.5 block text-sm text-stone-600">العنوان</label>
                        <input type="text" name="title" maxlength="255"
                               class="mb-4 w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-sm
                                      outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">

                        <label class="mb-1.5 block text-sm text-stone-600">رأيك في المنتج</label>
                        <textarea name="comment" rows="4" maxlength="2000"
                                  class="mb-4 w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-sm
                                         outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10"></textarea>

                        <button type="submit" class="w-full rounded-xl bg-stone-900 px-4 py-3 font-bold text-white transition hover:bg-stone-800">
                            أرسل التقييم
                        </button>
                        <p class="mt-2 text-xs text-stone-500">يظهر التقييم بعد مراجعته.</p>
                    </form>
                @else
                    <div class="rounded-3xl bg-white p-6 text-center shadow-soft">
                        <p class="text-sm text-stone-600">سجّل الدخول لتتمكن من تقييم المنتج.</p>
                        <a href="{{ route('login') }}"
                           class="mt-4 inline-block rounded-full bg-stone-900 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-stone-800">
                            تسجيل الدخول
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-16">
            <h2 class="mb-5 text-xl font-extrabold tracking-tight text-stone-900 sm:text-2xl">قد يعجبك أيضاً</h2>
            <div class="snap-row no-scrollbar -mx-4 px-4 pb-2 lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5
                        lg:overflow-visible lg:px-0">
                @foreach ($related as $item)
                    <div class="snap-row-item w-[62%] sm:w-[40%] lg:w-auto">
                        <x-product-card :product="$item" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
