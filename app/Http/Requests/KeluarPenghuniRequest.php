<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use Illuminate\Foundation\Http\FormRequest;

class KeluarPenghuniRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $penghuni = $this->route('penghuni');

        return $user !== null
            && $penghuni instanceof Penghuni
            && $user->can('kelolaKontrak', $penghuni);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tanggal_keluar' => ['required', 'date'],
            'alasan_keluar' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_keluar.required' => 'Tanggal keluar belum diisi.',
            'tanggal_keluar.date' => 'Format tanggal keluar tidak valid.',
            'alasan_keluar.max' => 'Alasan keluar maksimal 255 karakter.',
        ];
    }
}
