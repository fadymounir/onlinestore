@extends($shopLayout ?? 'layouts.shop')

@section('title', 'نسيت كلمة المرور')

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">نسيت كلمة المرور؟</h1>
        <p class="mb-6 text-sm leading-relaxed text-stone-600">
            اكتب بريدك الإلكتروني وسنرسل لك رابطاً لتعيين كلمة مرور جديدة.
        </p>

        <form action="{{ route('password.email') }}" method="POST"
              class="space-y-4 rounded-3xl bg-white p-6 shadow-soft">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-stone-700">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr"
                       class="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 outline-none focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
            </div>

            <button type="submit" class="w-full rounded-xl bg-stone-900 px-6 py-3 font-bold text-white transition hover:bg-stone-800">
                أرسل رابط إعادة التعيين
            </button>

            <p class="text-center text-sm text-stone-600">
                تذكّرتها؟
                <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">عُد لتسجيل الدخول</a>
            </p>
        </form>
    </div>
@endsection
