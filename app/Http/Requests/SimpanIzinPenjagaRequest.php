<?php

namespace App\Http\Requests;

use App\Models\Pengaturan;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;

class SimpanIzinPenjagaRequest extends FormRequest
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
            'jam_edit_penjaga' => ['required', 'integer', 'min:1', 'max:720'],
            'penjaga_kelola_kontrak' => ['nullable', 'boolean'],
            'penjaga_ubah_catatan_lain' => ['nullable', 'boolean'],
        ];
    }
}
