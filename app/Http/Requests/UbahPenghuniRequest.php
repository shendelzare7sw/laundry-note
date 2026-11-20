<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use Illuminate\Foundation\Http\FormRequest;

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
            'catatan' => ['nullable', 'string', 'max:500'],
            'tanggal_masuk_perkiraan' => ['sometimes', 'boolean'],
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
            'catatan.max' => 'Catatan penghuni maksimal 500 karakter.',
        ];
    }
}
