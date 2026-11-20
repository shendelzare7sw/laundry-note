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
}
