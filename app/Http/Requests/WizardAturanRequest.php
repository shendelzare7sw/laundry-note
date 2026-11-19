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
}
