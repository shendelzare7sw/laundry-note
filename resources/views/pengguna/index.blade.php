<x-app-layout judul="Kelola Pengguna">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">Kelola Pengguna</h1>
                <p class="text-sm text-muted">Buat akun staf properti, nonaktifkan akun, atau reset password</p>
            </div>

            <button
                type="button"
                x-data
                x-on:click="$dispatch('buka-modal', 'modal-tambah-pengguna')"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-4 py-2 text-sm font-bold text-white hover:bg-maroon-cerah"
            >
                + Tambah Akun
            </button>
        </div>

        @if ($errors->any())
            <x-kartu class="border-danger/30 bg-danger/5">
                <ul class="list-disc space-y-1 pl-5 text-sm text-danger">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-kartu>
        @endif

        <div class="space-y-3">
            @forelse ($daftarPengguna as $pengguna)
                <x-kartu>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-base font-bold text-ink">{{ $pengguna->name }}</span>
                                <x-lencana :status="$pengguna->aktif ? 'aktif' : 'nonaktif'" />
                            </div>
                            <div class="mt-1 text-xs text-muted">
                                Username: <span class="font-mono font-medium text-ink">{{ $pengguna->username }}</span>
                                · Peran: <span class="font-medium text-ink">{{ $pengguna->role->label() }}</span>
                                @if ($pengguna->properti)
                                    · Properti: <span class="font-medium text-ink">{{ $pengguna->properti->nama }}</span>
                                @endif
                            </div>
                        </div>

                        @can('update', $pengguna)
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('pengguna.toggle-aktif', $pengguna) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="min-h-11 rounded-xl border border-line-strong px-3 text-xs font-bold text-ink hover:bg-sunken"
                                    >
                                        {{ $pengguna->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <button
                                    type="button"
                                    x-data
                                    x-on:click="$dispatch('buka-modal', 'modal-reset-{{ $pengguna->id }}')"
                                    class="min-h-11 rounded-xl bg-gold-container px-3 text-xs font-bold text-on-gold hover:bg-gold/25"
                                >
                                    Reset Password
                                </button>
                            </div>

                            <x-modal id="modal-reset-{{ $pengguna->id }}" judul="Reset Password: {{ $pengguna->username }}">
                                <form method="POST" action="{{ route('pengguna.reset-password', $pengguna) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <x-field label="Password Baru" name="password" id="password-{{ $pengguna->id }}" type="password" :required="true" />
                                    <x-field label="Konfirmasi Password" name="password_confirmation" id="password-conf-{{ $pengguna->id }}" type="password" :required="true" />
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            x-on:click="$dispatch('tutup-modal', 'modal-reset-{{ $pengguna->id }}')"
                                            class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            type="submit"
                                            class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                                        >
                                            Simpan Password
                                        </button>
                                    </div>
                                </form>
                            </x-modal>
                        @endcan
                    </div>
                </x-kartu>
            @empty
                <x-kartu>
                    <p class="text-sm text-muted">Belum ada akun staf terdaftar.</p>
                </x-kartu>
            @endforelse
        </div>
    </div>

    <x-modal id="modal-tambah-pengguna" judul="Tambah Akun Pengguna">
        <form method="POST" action="{{ route('pengguna.store') }}" class="space-y-4">
            @csrf
            <x-field label="Nama Lengkap" name="name" :required="true" placeholder="Nama karyawan" />
            <x-field label="Username" name="username" :required="true" placeholder="Huruf kecil tanpa spasi" />
            <x-field label="Email (Opsional)" name="email" type="email" placeholder="Opsional" />
            <x-field label="Password Awal" name="password" type="password" :required="true" placeholder="Minimal 8 karakter" />

            <x-field label="Peran" name="role" :required="true">
                <select
                    id="role"
                    name="role"
                    class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                >
                    @foreach ($peranDiizinkan as $peran)
                        <option value="{{ $peran->value }}">{{ $peran->label() }}</option>
                    @endforeach
                </select>
            </x-field>

            @if ($daftarPropertiPilihan->isNotEmpty())
                <x-field label="Properti Penempatan" name="properti_id">
                    <select
                        id="properti_id"
                        name="properti_id"
                        class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                    >
                        @foreach ($daftarPropertiPilihan as $item)
                            <option value="{{ $item->id }}" @selected($propertiAktif?->id === $item->id)>
                                {{ $item->nama }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    x-on:click="$dispatch('tutup-modal', 'modal-tambah-pengguna')"
                    class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                >
                    Buat Akun
                </button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
