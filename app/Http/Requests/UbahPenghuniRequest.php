<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahPenghuniRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $penghuni = $this->route('penghuni');

        return $user !== null
            && $penghuni instanceof Penghuni
            && $user->can('kelolaPenghuni', $penghuni);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'alias' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['booking', 'aktif', 'keluar'])],
            'tanggal_masuk' => ['sometimes', 'required', 'date'],
            'tanggal_masuk_perkiraan' => ['sometimes', 'boolean'],
            'jangka_waktu_sewa' => ['nullable', Rule::in(array_keys(Penghuni::DAFTAR_JANGKA_WAKTU))],
            'tanggal_keluar_rencana' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'alasan_keluar' => ['nullable', 'string', 'max:255'],
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
            'status.in' => 'Status kontrak tidak valid.',
            'tanggal_masuk.required' => 'Tanggal masuk belum diisi.',
            'tanggal_masuk.date' => 'Format tanggal masuk tidak valid.',
            'jangka_waktu_sewa.in' => 'Pilihan jangka waktu sewa tidak valid.',
            'tanggal_keluar_rencana.date' => 'Format tanggal keluar rencana tidak valid.',
            'tanggal_keluar_rencana.after_or_equal' => 'Tanggal keluar rencana tidak boleh sebelum tanggal masuk.',
            'tanggal_keluar.date' => 'Format tanggal keluar tidak valid.',
            'tanggal_keluar.after_or_equal' => 'Tanggal keluar tidak boleh sebelum tanggal masuk.',
            'alasan_keluar.max' => 'Alasan keluar maksimal 255 karakter.',
            'catatan.max' => 'Catatan penghuni maksimal 500 karakter.',
        ];
    }
}
