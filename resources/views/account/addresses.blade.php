@extends('layouts.shop')

@section('title', 'عناويني')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-stone-900">عناويني</h1>

    <div class="grid gap-8 lg:grid-cols-[1fr_420px]">
        {{-- saved --}}
        <div class="space-y-4">
            @forelse ($addresses as $address)
                <div @class([
                    'rounded-2xl border bg-white p-5',
                    'border-amber-500' => $address->is_default,
                    'border-stone-200' => ! $address->is_default,
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-stone-900">
                                {{ $address->recipient_name }}
                                @if ($address->label)
                                    <span class="ms-2 rounded bg-stone-100 px-1.5 py-0.5 text-[11px] font-medium text-stone-600">{{ $address->label }}</span>
                                @endif
                                @if ($address->is_default)
                                    <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">افتراضي</span>
                                @endif
                            </p>
                            <p class="mt-2 text-sm text-stone-600">{{ $address->fullAddress() }}</p>
                            @if ($address->landmark)
                                <p class="mt-1 text-xs text-stone-500">{{ $address->landmark }}</p>
                            @endif
                            <p class="mt-1 text-sm text-stone-600" dir="ltr">{{ $address->phone }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('account.addresses.edit', $address) }}"
                               class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs text-stone-700 hover:bg-stone-100">
                                تعديل
                            </a>
                            <form action="{{ route('account.addresses.destroy', $address) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('حذف هذا العنوان؟')"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50">
                                    حذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <x-empty title="لا عناوين محفوظة"
                         description="أضف عنوانك من النموذج المجاور لتتمكن من إتمام الطلبات." />
            @endforelse
        </div>

        {{-- form --}}
        <aside>
            <form action="{{ $editing ? route('account.addresses.update', $editing) : route('account.addresses.store') }}"
                  method="POST" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <h2 class="font-bold text-stone-900">{{ $editing ? 'تعديل العنوان' : 'إضافة عنوان جديد' }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-stone-700">وسم العنوان</label>
                        <input type="text" name="label" value="{{ old('label', $editing?->label) }}" placeholder="المنزل / العمل"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">اسم المستلم *</label>
                        <input type="text" name="recipient_name" required
                               value="{{ old('recipient_name', $editing?->recipient_name ?? auth()->user()->name) }}"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">رقم الموبايل *</label>
                        <input type="tel" name="phone" required dir="ltr" placeholder="01012345678"
                               value="{{ old('phone', $editing?->phone ?? auth()->user()->phone) }}"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">هاتف بديل</label>
                        <input type="tel" name="alternate_phone" dir="ltr"
                               value="{{ old('alternate_phone', $editing?->alternate_phone) }}"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div>
                        <label for="governorate_id" class="mb-1 block text-sm font-medium text-stone-700">المحافظة *</label>
                        <select name="governorate_id" id="governorate_id" required
                                class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                            <option value="">اختر المحافظة</option>
                            @foreach ($governorates as $governorate)
                                <option value="{{ $governorate->id }}"
                                    @selected(old('governorate_id', $editing?->governorate_id) == $governorate->id)>
                                    {{ $governorate->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="city_id" class="mb-1 block text-sm font-medium text-stone-700">المدينة</label>
                        <select name="city_id" id="city_id"
                                class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                            <option value="">اختر المدينة</option>
                            @foreach ($governorates as $governorate)
                                <optgroup label="{{ $governorate->name }}">
                                    @foreach ($cities[$governorate->id] ?? [] as $city)
                                        <option value="{{ $city->id }}"
                                            @selected(old('city_id', $editing?->city_id) == $city->id)>
                                            {{ $city->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-stone-500">المدن مجمّعة تحت كل محافظة.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-stone-700">الشارع *</label>
                        <input type="text" name="street" required value="{{ old('street', $editing?->street) }}"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">العقار</label>
                        <input type="text" name="building" value="{{ old('building', $editing?->building) }}"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-stone-700">الدور</label>
                            <input type="text" name="floor" value="{{ old('floor', $editing?->floor) }}"
                                   class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-stone-700">الشقة</label>
                            <input type="text" name="apartment" value="{{ old('apartment', $editing?->apartment) }}"
                                   class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-stone-700">علامة مميّزة</label>
                        <input type="text" name="landmark" value="{{ old('landmark', $editing?->landmark) }}"
                               placeholder="أمام / بجوار…"
                               class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-amber-600">
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_default" value="1"
                           @checked(old('is_default', $editing?->is_default))
                           class="size-4 rounded border-stone-300 text-amber-600 focus:ring-amber-600">
                    اجعله العنوان الافتراضي
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white hover:bg-amber-700">
                        {{ $editing ? 'حفظ التعديلات' : 'حفظ العنوان' }}
                    </button>
                    @if ($editing)
                        <a href="{{ route('account.addresses.index') }}"
                           class="rounded-lg border border-stone-300 px-5 py-3 text-sm text-stone-700 hover:bg-stone-100">
                            إلغاء
                        </a>
                    @endif
                </div>
            </form>
        </aside>
    </div>
@endsection
