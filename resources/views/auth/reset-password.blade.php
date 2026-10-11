@extends('layouts.shop')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-bold text-stone-900">تعيين كلمة مرور جديدة</h1>
        <p class="mb-6 text-sm text-stone-600">اختر كلمة مرور قوية لا تستخدمها في مكان آخر.</p>

        <form action="{{ route('password.update') }}" method="POST"
              class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly dir="ltr"
                       class="w-full rounded-lg border border-stone-300 bg-stone-100 px-3 py-2.5 text-stone-600 outline-none">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-stone-700">كلمة المرور الجديدة</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
                <p class="mt-1 text-xs text-stone-500">8 أحرف على الأقل.</p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700">تأكيد كلمة المرور</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/20">
            </div>

            <button type="submit" class="w-full rounded-lg bg-amber-600 px-6 py-3 font-semibold text-white hover:bg-amber-700">
                حفظ كلمة المرور
            </button>
        </form>
    </div>
@endsection
