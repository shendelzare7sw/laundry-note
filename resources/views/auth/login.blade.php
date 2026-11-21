<x-guest-layout judul="Masuk">
    <x-kartu>
        @if (session('auth_blocked'))
            <div role="alert" class="mb-4 flex items-start gap-2.5 rounded-xl border border-danger/35 bg-danger/10 p-3.5 text-xs text-danger">
                <i class="fas fa-ban mt-0.5 shrink-0 text-sm" aria-hidden="true"></i>
                <div>
                    <p class="font-bold">Akses Masuk Ditolak</p>
                    <p class="mt-0.5 leading-relaxed">{{ session('auth_blocked') }}</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <x-field
                label="Username"
                name="username"
                type="text"
                :required="true"
                autocomplete="username"
                placeholder="Masukkan username"
                autofocus
            />

            <x-field
                label="Password"
                name="password"
                type="password"
                :required="true"
                autocomplete="current-password"
                placeholder="Masukkan password"
            />

            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                >
                <span>Ingat saya di perangkat ini</span>
            </label>

            <button
                type="submit"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-maroon px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-maroon-cerah focus-visible:outline-2 focus-visible:outline-maroon"
            >
                <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                <span>Masuk</span>
            </button>
        </form>
    </x-kartu>
</x-guest-layout>
