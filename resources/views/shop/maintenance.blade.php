<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المتجر مغلق مؤقتاً — {{ $storeName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-50 px-4 font-sans text-stone-800">
    <div class="max-w-md text-center">
        <img src="{{ asset('images/logo-256.png') }}" alt="" width="256" height="256"
             class="mx-auto mb-5 size-28 rounded-full">
        <h1 class="text-2xl font-bold text-stone-900">{{ $storeName }}</h1>
        <p class="mt-4 text-lg font-semibold">المتجر مغلق مؤقتاً للصيانة</p>
        <p class="mt-2 leading-relaxed text-stone-600">
            نعمل على تحديث المتجر وسنعود قريباً. شكراً لصبرك.
        </p>
        @if ($storePhone)
            <p class="mt-6 text-sm text-stone-500">
                للاستفسار: <a href="tel:{{ $storePhone }}" dir="ltr" class="font-semibold text-amber-700">{{ $storePhone }}</a>
            </p>
        @endif
    </div>
</body>
</html>
