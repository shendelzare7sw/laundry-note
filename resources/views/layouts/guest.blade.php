<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Masuk' }} — Catatan Laundry</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col justify-center bg-bg px-4 py-8 font-sans text-ink antialiased">
    <div class="mx-auto w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-maroon text-lg font-bold text-gold-soft shadow-xs">
                CL
            </div>
            <h1 class="mt-3 text-xl font-bold text-ink">Catatan Laundry</h1>
            <p class="mt-1 text-sm text-muted">Masuk dengan akun yang telah didaftarkan admin</p>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
