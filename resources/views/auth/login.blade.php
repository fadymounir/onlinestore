@extends($shopLayout ?? 'layouts.shop')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">تسجيل الدخول</h1>
        <p class="mb-6 text-sm text-stone-600">أدخل بياناتك لمتابعة طلباتك وإتمام الشراء.</p>

        <form action="{{ route('login') }}" method="POST" class="space-y-4 rounded-3xl bg-white p-6 shadow-soft">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <div>
                <div class="mb-1 flex items-baseline justify-between gap-2">
                    <label for="password" class="block text-sm font-medium text-stone-700">كلمة المرور</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-700 hover:underline">
                        نسيت كلمة المرور؟
                    </a>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" value="1"
                       class="size-4 accent-brand-600">
                تذكّرني
            </label>

            <button type="submit" class="w-full rounded-xl bg-stone-900 px-6 py-3 font-bold text-white transition hover:bg-stone-800">
                دخول
            </button>

            <p class="text-center text-sm text-stone-600">
                ليس لديك حساب؟
                <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:underline">أنشئ حساباً جديداً</a>
            </p>
        </form>
    </div>
@endsection
