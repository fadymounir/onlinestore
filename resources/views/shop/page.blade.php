@extends($shopLayout ?? 'layouts.shop')

@section('title', $page->title)
@section('description', $page->meta_description ?? Str::limit(strip_tags($page->content), 155))

@section('content')
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-stone-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">الرئيسية</a>
        <span>/</span>
        <span class="text-stone-900">{{ $page->title }}</span>
    </nav>

    {{-- Narrower than the grid pages on purpose: prose stops being readable
         much past 70 characters a line. --}}
    <article class="mx-auto max-w-3xl rounded-3xl bg-white p-6 shadow-soft sm:p-10">
        <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl sm:text-3xl">{{ $page->title }}</h1>

        <p class="mt-2 text-xs text-stone-400">
            آخر تحديث: {{ $page->updated_at->translatedFormat('j F Y') }}
        </p>

        <hr class="my-6 border-stone-200">

        {{-- Unescaped: this is the rich-editor HTML an admin wrote. Authoring
             is behind the admin guard — see App\Models\Page. --}}
        <div class="prose-shop">{!! $page->content !!}</div>
    </article>
@endsection
