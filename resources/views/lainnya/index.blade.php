<x-app-layout judul="Lainnya">
    <div class="space-y-4">
        <h1 class="text-xl font-bold text-ink">Menu Lainnya</h1>

        <div class="grid gap-3 sm:grid-cols-2">
            @can('viewAny', \App\Models\User::class)
                <a href="{{ route('pengguna.index') }}" class="block rounded-2xl border border-line bg-surface p-4 shadow-xs hover:bg-alt">
                    <div class="text-base font-bold text-ink">Kelola Pengguna</div>
                    <p class="mt-1 text-xs text-muted">Tambah staf, nonaktifkan akun, dan reset password</p>
                </a>
            @endcan

            @can('viewAny', \App\Models\Properti::class)
                <a href="{{ route('properti.index') }}" class="block rounded-2xl border border-line bg-surface p-4 shadow-xs hover:bg-alt">
                    <div class="text-base font-bold text-ink">Kelola Properti</div>
                    <p class="mt-1 text-xs text-muted">Kelola daftar properti dan ganti properti aktif</p>
                </a>
            @endcan
        </div>

        <x-kartu judul="Akun & Sesi">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm">
                    <div class="font-bold text-ink">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-muted">{{ auth()->user()->username }} · {{ auth()->user()->role->label() }}</div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                    >
                        Keluar
                    </button>
                </form>
            </div>
        </x-kartu>
    </div>
</x-app-layout>
