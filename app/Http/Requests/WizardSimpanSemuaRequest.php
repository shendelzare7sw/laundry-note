<?php

namespace App\Http\Requests;

use App\Enums\Peran;
use App\Models\Properti;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WizardSimpanSemuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->route('properti');

        return $user !== null
            && $properti instanceof Properti
            && $user->can('update', $properti);
    }

    protected function prepareForValidation(): void
    {
        $nama = trim((string) $this->input('nama', ''));
        $namaLaporan = trim((string) $this->input('nama_properti_laporan', ''));
        $nameStaf = trim((string) $this->input('name', ''));
        $usernameStaf = Str::lower(trim((string) $this->input('username', '')));
        $passwordStaf = (string) $this->input('password', '');

        $this->merge([
            'nama' => $nama,
            'nama_properti_laporan' => $namaLaporan !== '' ? $namaLaporan : $nama,
            'name' => $nameStaf !== '' ? $nameStaf : null,
            'username' => $usernameStaf !== '' ? $usernameStaf : null,
            'password' => $passwordStaf !== '' ? $passwordStaf : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'nama_properti_laporan' => ['required', 'string', 'max:120'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draf', 'aktif', 'arsip'])],

            'tarif_per_kg' => ['required', 'integer', 'min:100', 'max:1000000'],
            'min_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'kuota_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'ambang_mendekati_persen' => ['required', 'integer', 'min:1', 'max:100'],
            'toleransi_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'pembulatan' => ['required', 'integer', 'min:1', 'max:100000'],
            'mode_kuota' => ['required', Rule::in(['penghuni', 'tanpa_kuota'])],
            'jam_edit_penjaga' => ['required', 'integer', 'min:1', 'max:168'],
            'penjaga_kelola_kontrak' => ['nullable', 'boolean'],

            'name' => ['nullable', 'required_with:username,password', 'string', 'max:120'],
            'username' => [
                'nullable',
                'required_with:name,password',
                'string',
                'min:3',
                'max:50',
                'regex:/^\S+$/',
                Rule::unique('users', 'username'),
            ],
            'password' => ['nullable', 'required_with:name,username', 'string', 'min:8'],
            'role' => [
                'nullable',
                'required_with:name,username,password',
                Rule::in([Peran::Admin->value, Peran::Penjaga->value]),
            ],
            'aktifkan_sekarang' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama properti belum diisi.',
            'nama.max' => 'Nama properti maksimal 120 karakter.',
            'nama_properti_laporan.required' => 'Nama properti di laporan belum diisi.',
            'nama_properti_laporan.max' => 'Nama properti di laporan maksimal 120 karakter.',
            'alamat.max' => 'Alamat properti maksimal 500 karakter.',
            'status.in' => 'Status operasional properti yang dipilih tidak valid.',
            'tarif_per_kg.required' => 'Tarif laundry per kg belum diisi.',
            'tarif_per_kg.integer' => 'Tarif laundry per kg harus berupa angka bulat rupiah.',
            'tarif_per_kg.min' => 'Tarif laundry per kg minimal Rp100.',
            'tarif_per_kg.max' => 'Tarif laundry per kg maksimal Rp1.000.000.',
            'min_kg.required' => 'Minimum berat laundry belum diisi.',
            'min_kg.numeric' => 'Minimum berat laundry harus berupa angka.',
            'min_kg.min' => 'Minimum berat laundry tidak boleh kurang dari 0 kg.',
            'min_kg.max' => 'Minimum berat laundry maksimal 50 kg.',
            'kuota_kg.required' => 'Kuota bulanan belum diisi.',
            'kuota_kg.numeric' => 'Kuota bulanan harus berupa angka.',
            'kuota_kg.min' => 'Kuota bulanan tidak boleh kurang dari 0 kg.',
            'kuota_kg.max' => 'Kuota bulanan maksimal 500 kg.',
            'ambang_mendekati_persen.required' => 'Ambang mendekati kuota (%) belum diisi.',
            'ambang_mendekati_persen.integer' => 'Ambang mendekati kuota harus berupa angka bulat persen.',
            'ambang_mendekati_persen.min' => 'Ambang mendekati kuota harus di antara 1% sampai 100%.',
            'ambang_mendekati_persen.max' => 'Ambang mendekati kuota harus di antara 1% sampai 100%.',
            'toleransi_kg.required' => 'Toleransi lewat kuota belum diisi.',
            'toleransi_kg.numeric' => 'Toleransi lewat kuota harus berupa angka.',
            'toleransi_kg.min' => 'Toleransi lewat kuota tidak boleh negatif.',
            'toleransi_kg.max' => 'Toleransi lewat kuota maksimal 50 kg.',
            'pembulatan.required' => 'Pembulatan tagihan belum diisi.',
            'pembulatan.integer' => 'Pembulatan tagihan harus berupa angka bulat rupiah.',
            'pembulatan.min' => 'Pembulatan tagihan minimal Rp1.',
            'pembulatan.max' => 'Pembulatan tagihan maksimal Rp100.000.',
            'mode_kuota.required' => 'Mode kuota belum dipilih.',
            'mode_kuota.in' => 'Pilihan mode kuota tidak valid.',
            'jam_edit_penjaga.required' => 'Batas jam edit penjaga belum diisi.',
            'jam_edit_penjaga.integer' => 'Batas jam edit penjaga harus berupa angka bulat.',
            'jam_edit_penjaga.min' => 'Batas jam edit penjaga harus antara 1 sampai 168 jam.',
            'jam_edit_penjaga.max' => 'Batas jam edit penjaga harus antara 1 sampai 168 jam.',
            'name.required_with' => 'Nama karyawan belum diisi (wajib dilengkapi jika ingin menambah staf baru).',
            'name.max' => 'Nama karyawan maksimal 120 karakter.',
            'username.required_with' => 'Username karyawan belum diisi (wajib dilengkapi jika ingin menambah staf baru).',
            'username.min' => 'Username karyawan minimal 3 karakter.',
            'username.max' => 'Username karyawan maksimal 50 karakter.',
            'username.regex' => 'Username karyawan tidak boleh mengandung spasi (karakter unik seperti @, #, $, ., _, - diperbolehkan).',
            'username.unique' => 'Username tersebut sudah dipakai akun lain. Silakan pilih username berbeda.',
            'password.required_with' => 'Password awal belum diisi (wajib dilengkapi jika nama atau username karyawan diisi).',
            'password.min' => 'Password awal minimal 8 karakter.',
            'role.required_with' => 'Peran staf belum dipilih.',
            'role.in' => 'Peran staf yang dipilih tidak valid.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $properti = $this->route('properti');

                if (! $properti instanceof Properti) {
                    return;
                }

                if ($this->filled('username') && $this->input('role') === Peran::Admin->value) {
                    $sudahAdaAdmin = User::query()
                        ->where('properti_id', $properti->id)
                        ->where('role', Peran::Admin->value)
                        ->where('aktif', true)
                        ->exists();

                    if ($sudahAdaAdmin) {
                        $validator->errors()->add(
                            'role',
                            'Properti ini sudah memiliki admin aktif. Nonaktifkan admin lama terlebih dahulu.'
                        );
                    }
                }
            },
        ];
    }
}
