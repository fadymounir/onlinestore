@extends($shopLayout ?? 'layouts.shop')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">تعيين كلمة مرور جديدة</h1>
        <p class="mb-6 text-sm text-stone-600">اختر كلمة مرور قوية لا تستخدمها في مكان آخر.</p>

        <form action="{{ route('password.update') }}" method="POST"
              class="space-y-4 rounded-3xl bg-white p-6 shadow-soft">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly dir="ltr"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 bg-stone-100 px-3 py-2.5 text-stone-600 outline-none">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-stone-700">كلمة المرور الجديدة</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                <p class="mt-1 text-xs text-stone-500">8 أحرف على الأقل.</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <button type="submit" class="w-full rounded-xl bg-stone-900 px-6 py-3 font-bold text-white transition hover:bg-stone-800">
                حفظ كلمة المرور
            </button>
        </form>
    </div>
@endsection
