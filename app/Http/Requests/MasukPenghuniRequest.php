<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasukPenghuniRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->propertiAktif();

        return $user !== null
            && $properti !== null
            && $user->can('kelolaKontrak', [Penghuni::class, $properti]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $properti = $this->propertiAktif();

        return [
            'nama' => ['required', 'string', 'max:120'],
            'alias' => ['nullable', 'string', 'max:120'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['booking', 'aktif'])],
            'kamar_id' => [
                'required',
                'integer',
                Rule::exists('kamar', 'id')->where(fn ($query) => $query->where('properti_id', $properti?->id)),
            ],
            'tanggal_masuk' => ['required', 'date'],
            'tanggal_masuk_perkiraan' => ['sometimes', 'boolean'],
            'jangka_waktu_sewa' => ['nullable', Rule::in(array_keys(Penghuni::DAFTAR_JANGKA_WAKTU))],
            'tanggal_keluar_rencana' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama' => 'nama penghuni',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama lengkap penghuni belum diisi.',
            'nama.max' => 'Nama lengkap penghuni maksimal 120 karakter.',
            'alias.max' => 'Nama panggilan / alias maksimal 120 karakter.',
            'no_wa.max' => 'Nomor WhatsApp maksimal 30 karakter.',
            'status.in' => 'Status kontrak harus Booking atau Aktif.',
            'kamar_id.required' => 'Kamar tujuan belum dipilih.',
            'kamar_id.integer' => 'Pilihan kamar tujuan tidak valid.',
            'kamar_id.exists' => 'Kamar yang dipilih tidak valid atau bukan milik properti ini.',
            'tanggal_masuk.required' => 'Tanggal masuk belum diisi.',
            'tanggal_masuk.date' => 'Format tanggal masuk tidak valid.',
            'jangka_waktu_sewa.in' => 'Pilihan jangka waktu sewa tidak valid.',
            'tanggal_keluar_rencana.date' => 'Format tanggal keluar rencana tidak valid.',
            'tanggal_keluar_rencana.after_or_equal' => 'Tanggal keluar rencana tidak boleh sebelum tanggal masuk.',
            'catatan.max' => 'Catatan penghuni maksimal 500 karakter.',
        ];
    }

    private function propertiAktif(): ?Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        return $properti instanceof Properti ? $properti : null;
    }
}
