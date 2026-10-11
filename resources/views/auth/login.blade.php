@extends('layouts.shop')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-bold text-stone-900">تسجيل الدخول</h1>
        <p class="mb-6 text-sm text-stone-600">أدخل بياناتك لمتابعة طلباتك وإتمام الشراء.</p>

        <form action="{{ route('login') }}" method="POST" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <div>
                <div class="mb-1 flex items-baseline justify-between gap-2">
                    <label for="password" class="block text-sm font-medium text-stone-700">كلمة المرور</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-amber-700 hover:underline">
                        نسيت كلمة المرور؟
                    </a>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" value="1"
                       class="size-4 rounded border-stone-300 text-amber-600 focus:ring-amber-600">
                تذكّرني
            </label>

            <button type="submit" class="w-full rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white hover:bg-amber-700">
                دخول
            </button>

            <p class="text-center text-sm text-stone-600">
                ليس لديك حساب؟
                <a href="{{ route('register') }}" class="font-semibold text-amber-700 hover:underline">أنشئ حساباً جديداً</a>
            </p>
        </form>
    </div>
@endsection
