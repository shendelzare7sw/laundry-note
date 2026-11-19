<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Masuk' }} — {{ config('app.name', 'Laundry-Kos') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col justify-center bg-bg px-4 py-8 font-sans text-ink antialiased">
    <div class="mx-auto w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="sr-only">{{ config('app.name', 'Laundry-Kos') }}</h1>
            <div class="mx-auto inline-flex items-center justify-center rounded-2xl border border-line bg-surface p-2 shadow-xs">
                <img
                    src="{{ asset('img/logo-app.jpg') }}"
                    alt="{{ config('app.name', 'Laundry-Kos') }}"
                    class="h-28 w-28 rounded-xl object-contain"
                >
            </div>
            <p class="mt-3 text-sm text-muted">Masuk dengan akun yang telah didaftarkan admin</p>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
