<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PindahKamarRequest extends FormRequest
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
        $penghuni = $this->route('penghuni');
        $propertiId = $penghuni instanceof Penghuni ? $penghuni->properti_id : null;

        return [
            'kamar_tujuan_id' => [
                'required',
                'integer',
                Rule::exists('kamar', 'id')->where(fn ($query) => $query->where('properti_id', $propertiId)),
            ],
            'tanggal_pindah' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kamar_tujuan_id.required' => 'Kamar tujuan pindah belum dipilih.',
            'kamar_tujuan_id.integer' => 'Pilihan kamar tujuan pindah tidak valid.',
            'kamar_tujuan_id.exists' => 'Kamar tujuan pindah tidak valid atau bukan milik properti ini.',
            'tanggal_pindah.required' => 'Tanggal efektif pindah belum diisi.',
            'tanggal_pindah.date' => 'Format tanggal efektif pindah tidak valid.',
        ];
    }
}
