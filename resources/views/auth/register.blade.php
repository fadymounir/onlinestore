@extends($shopLayout ?? 'layouts.shop')

@section('title', 'إنشاء حساب')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">إنشاء حساب جديد</h1>
        <p class="mb-6 text-sm text-stone-600">الحساب يلزم لإتمام الطلب ومتابعة الشحن.</p>

        <form action="{{ route('register') }}" method="POST" class="space-y-4 rounded-3xl bg-white p-6 shadow-soft">
            @csrf

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-stone-700">الاسم بالكامل</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <div>
                <label for="phone" class="mb-1 block text-sm font-medium text-stone-700">رقم الموبايل</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required dir="ltr"
                       placeholder="01012345678"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                <p class="mt-1 text-xs text-stone-500">سنستخدمه لتأكيد الطلب والتوصيل.</p>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-stone-700">كلمة المرور</label>
                <input type="password" id="password" name="password" required
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                <p class="mt-1 text-xs text-stone-500">8 أحرف على الأقل.</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <button type="submit" class="w-full rounded-xl bg-stone-900 px-6 py-3 font-bold text-white transition hover:bg-stone-800">
                إنشاء الحساب
            </button>

            <p class="text-center text-sm text-stone-600">
                لديك حساب؟
                <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">سجّل الدخول</a>
            </p>
        </form>
    </div>
@endsection
