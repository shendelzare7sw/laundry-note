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
                'alpha_dash',
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
