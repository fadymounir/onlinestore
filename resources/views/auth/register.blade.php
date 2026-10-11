@extends('layouts.shop')

@section('title', 'إنشاء حساب')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-bold text-stone-900">إنشاء حساب جديد</h1>
        <p class="mb-6 text-sm text-stone-600">الحساب يلزم لإتمام الطلب ومتابعة الشحن.</p>

        <form action="{{ route('register') }}" method="POST" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            @csrf

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-stone-700">الاسم بالكامل</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <div>
                <label for="phone" class="mb-1 block text-sm font-medium text-stone-700">رقم الموبايل</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required dir="ltr"
                       placeholder="01012345678"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
                <p class="mt-1 text-xs text-stone-500">سنستخدمه لتأكيد الطلب والتوصيل.</p>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-stone-700">كلمة المرور</label>
                <input type="password" id="password" name="password" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
                <p class="mt-1 text-xs text-stone-500">8 أحرف على الأقل.</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <button type="submit" class="w-full rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white hover:bg-amber-700">
                إنشاء الحساب
            </button>

            <p class="text-center text-sm text-stone-600">
                لديك حساب؟
                <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:underline">سجّل الدخول</a>
            </p>
        </form>
    </div>
@endsection
