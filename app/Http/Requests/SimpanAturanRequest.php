<?php

namespace App\Http\Requests;

use App\Models\Pengaturan;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;

class SimpanAturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        return $properti instanceof Properti
            && ($this->user()?->can('manage', [Pengaturan::class, $properti]) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'berlaku_mulai' => ['required', 'date_format:Y-m-d'],
            'nama_properti' => ['required', 'string', 'max:120'],
            'tarif_per_kg' => ['required', 'integer', 'min:100', 'max:1000000'],
            'min_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'kuota_kg' => ['required', 'numeric', 'min:0', 'max:500'],
            'ambang_mendekati_persen' => ['required', 'integer', 'min:1', 'max:100'],
            'toleransi_kg' => ['required', 'numeric', 'min:0', 'max:50'],
            'pembulatan' => ['required', 'integer', 'in:1,100,500,1000,5000'],
            'mode_kuota' => ['required', 'in:penghuni,tanpa_kuota'],
            'kuota_prorata_bulan_masuk' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['min_kg', 'kuota_kg', 'toleransi_kg'] as $kolom) {
            if ($this->has($kolom) && is_string($this->input($kolom))) {
                $this->merge([$kolom => str_replace(',', '.', trim($this->input($kolom)))]);
            }
        }

        foreach (['tarif_per_kg', 'pembulatan'] as $kolom) {
            if ($this->has($kolom) && is_string($this->input($kolom))) {
                $teks = trim($this->input($kolom));
                if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $teks) === 1) {
                    $this->merge([$kolom => str_replace('.', '', $teks)]);
                }
            }
        }
    }
}
