<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianKuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $penghuni = $this->route('penghuni');

        return $user !== null
            && $penghuni instanceof Penghuni
            && $user->can('sesuaikanKuota', $penghuni);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bulan' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'reset' => ['sometimes', 'boolean'],
            'nominal' => ['nullable', 'required_unless:reset,1', 'integer', 'min:0', 'max:10000000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
