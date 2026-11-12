{{--
    The page public/sw.js shows when a navigation cannot reach the server.

    Two things make it unlike every other view in this folder.

    It extends nothing and links no stylesheet. The service worker precaches
    this page at install and serves it from the device with the network down —
    so anything it had to fetch to look right would arrive broken. The styles
    below are therefore inline, and they are the only copy of these rules in
    the project; they repeat a handful of tokens from resources/css/app.css
    rather than share them, because sharing would mean a request.

    Its mark is the bundled badge, not $storeLogo. An uploaded logo lives under
    /storage, which the worker deliberately never caches (uploaded media is
    unbounded, and some of it is private), so the uploaded file is exactly the
    one that would not be there. The badge in public/images is precached and
    always will be.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>لا يوجد اتصال — {{ $storeName }}</title>
    <meta name="theme-color" content="#1c1917">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <style>
        :root {
            --paper: #faf8f5;
            --ink: #1c1917;
            --brand: #ad6a22;
            --brand-dark: #8b501f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem calc(1.5rem + env(safe-area-inset-right)) calc(1.5rem + env(safe-area-inset-bottom))
                     calc(1.5rem + env(safe-area-inset-left));
            background: var(--paper);
            color: #44403c;
            /* Cairo is a build asset and may not have loaded; the stack behind
               it is what an Arabic phone already has installed. */
            font-family: 'Cairo', 'Segoe UI', Tahoma, system-ui, sans-serif;
            line-height: 1.75;
            text-align: center;
            -webkit-font-smoothing: antialiased;
        }

        .card { max-width: 24rem; }

        .mark {
            inline-size: 7rem;
            block-size: 7rem;
            border-radius: 9999px;
            box-shadow: 0 2px 4px rgb(28 25 23 / 0.04), 0 18px 40px -20px rgb(28 25 23 / 0.3);
        }

        h1 {
            margin: 1.5rem 0 0;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
        }

        p { margin: 0.75rem 0 0; }

        .hint {
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #78716c;
        }

        .retry {
            margin-top: 1.75rem;
            display: inline-block;
            padding: 0.75rem 2rem;
            border: 0;
            border-radius: 9999px;
            background: var(--brand);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .retry:hover { background: var(--brand-dark); }

        .retry:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
    </style>
</head>
<body>
    <div class="card">
        <img class="mark" src="{{ asset('images/logo-192.png') }}" alt="" width="192" height="192">

        <h1>لا يوجد اتصال بالإنترنت</h1>

        <p>لم نتمكن من الوصول إلى {{ $storeName }}. تأكد من اتصالك ثم حاول مرة أخرى.</p>

        {{-- Reload, not history.back(): going back lands on the page that just
             failed, from the same empty cache. This re-requests the page the
             visitor actually wanted, and the worker passes it to the network
             untouched. --}}
        <button class="retry" type="button" onclick="location.reload()">إعادة المحاولة</button>

        {{-- Honest about what is kept: the worker caches no pages at all, so
             nothing is browsable offline. What it does do is come straight
             back the moment the connection returns. --}}
        <p class="hint">سنعيد تحميل الصفحة تلقائياً بمجرد عودة الاتصال.</p>
    </div>

    <script>
        // Come back on our own the moment the device reports a connection, so
        // a visitor who walked back into coverage is not left reading this.
        addEventListener('online', () => location.reload());
    </script>
</body>
</html>
