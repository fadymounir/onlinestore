@extends('layouts.shop')

@section('title', 'نسيت كلمة المرور')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-bold text-stone-900">نسيت كلمة المرور؟</h1>
        <p class="mb-6 text-sm leading-relaxed text-stone-600">
            اكتب بريدك الإلكتروني وسنرسل لك رابطاً لتعيين كلمة مرور جديدة.
        </p>

        <form action="{{ route('password.email') }}" method="POST"
              class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <button type="submit" class="w-full rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white hover:bg-amber-700">
                أرسل رابط إعادة التعيين
            </button>

            <p class="text-center text-sm text-stone-600">
                تذكّرتها؟
                <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:underline">عُد لتسجيل الدخول</a>
            </p>
        </form>
    </div>
@endsection
