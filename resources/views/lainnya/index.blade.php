<x-app-layout judul="Menu Lainnya" :subjudul="$propertiAktif?->nama ?? 'Pengaturan akun dan manajemen'">
    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @if ($propertiAktif && auth()->user()->can('manage', [\App\Models\Pengaturan::class, $propertiAktif]))
                <a href="{{ route('pengaturan.index') }}" class="flex items-start gap-3 rounded-2xl border border-line bg-surface p-4 shadow-xs hover:bg-alt">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-maroon/10 text-maroon">
                        <i class="fas fa-gear" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="text-base font-bold text-ink">Pengaturan Properti</div>
                        <p class="mt-0.5 text-xs text-muted">Aturan tarif & kuota berversi, izin penjaga, dan template WhatsApp</p>
                    </div>
                </a>
            @endif

            @can('viewAny', \App\Models\User::class)
                <a href="{{ route('pengguna.index') }}" class="flex items-start gap-3 rounded-2xl border border-line bg-surface p-4 shadow-xs hover:bg-alt">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-maroon/10 text-maroon">
                        <i class="fas fa-user-gear" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="text-base font-bold text-ink">Kelola Pengguna</div>
                        <p class="mt-0.5 text-xs text-muted">Tambah staf, nonaktifkan akun, dan reset password</p>
                    </div>
                </a>
            @endcan

            @can('viewAny', \App\Models\Properti::class)
                <a href="{{ route('properti.index') }}" class="flex items-start gap-3 rounded-2xl border border-line bg-surface p-4 shadow-xs hover:bg-alt">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold-container text-on-gold">
                        <i class="fas fa-building" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="text-base font-bold text-ink">Kelola / Pilih Properti</div>
                        <p class="mt-0.5 text-xs text-muted">Kelola daftar properti dan ganti properti aktif</p>
                    </div>
                </a>
            @endcan
        </div>

        <x-kartu judul="Akun & Sesi">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm">
                    <div class="font-bold text-ink">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-muted">{{ auth()->user()->username }} · {{ auth()->user()->role->label() }}</div>
                </div>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    data-confirm-title="Keluar dari Laundry-Kos?"
                    data-confirm-message="Sesi login Anda akan diakhiri dan Anda perlu masuk kembali untuk mengakses aplikasi."
                    data-confirm-label="Ya, Keluar"
                    data-confirm-variant="danger"
                >
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                    >
                        <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </x-kartu>
    </div>
</x-app-layout>
