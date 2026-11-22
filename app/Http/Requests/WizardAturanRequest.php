<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardAturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->route('properti');

        return $user !== null && $properti !== null && $user->can('update', $properti);
    }

    protected function prepareForValidation(): void
    {
        foreach (['tarif_per_kg', 'pembulatan'] as $kolom) {
            if ($this->has($kolom) && is_string($this->input($kolom))) {
                $teks = trim($this->input($kolom));
                if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $teks) === 1) {
                    $this->merge([$kolom => str_replace('.', '', $teks)]);
                }
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tarif_per_kg' => ['required', 'integer', 'min:100', 'max:1000000'],
            'min_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'kuota_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'ambang_mendekati_persen' => ['required', 'integer', 'min:1', 'max:100'],
            'toleransi_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'pembulatan' => ['required', 'integer', 'min:1', 'max:100000'],
            'mode_kuota' => ['required', Rule::in(['penghuni', 'tanpa_kuota'])],
            'jam_edit_penjaga' => ['required', 'integer', 'min:1', 'max:168'],
            'penjaga_kelola_kontrak' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
        ];
    }
}
