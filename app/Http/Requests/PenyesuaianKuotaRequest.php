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

    protected function prepareForValidation(): void
    {
        if ($this->has('nominal') && is_string($this->input('nominal'))) {
            $teks = trim($this->input('nominal'));
            if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $teks) === 1) {
                $this->merge(['nominal' => str_replace('.', '', $teks)]);
            }
        }
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bulan.required' => 'Periode bulan belum dipilih.',
            'bulan.regex' => 'Format periode bulan harus YYYY-MM.',
            'nominal.required_unless' => 'Nominal tagihan manual belum diisi.',
            'nominal.integer' => 'Nominal tagihan harus berupa angka bulat rupiah.',
            'nominal.min' => 'Nominal tagihan tidak boleh bernilai negatif.',
            'nominal.max' => 'Nominal tagihan maksimal Rp10.000.000.',
            'catatan.max' => 'Catatan penyesuaian maksimal 255 karakter.',
        ];
    }
}
