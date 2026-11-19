<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WizardKamarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->route('properti');

        return $user !== null && $properti !== null && $user->can('update', $properti);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lantai' => ['required', 'array', 'min:1'],
            'lantai.*.lantai' => ['required', 'integer', 'min:1', 'max:99'],
            'lantai.*.dari' => ['required', 'integer', 'min:1', 'max:9999'],
            'lantai.*.sampai' => ['required', 'integer', 'min:1', 'max:9999', 'gte:lantai.*.dari'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ((array) $this->input('lantai', []) as $index => $baris) {
                    $jumlah = ((int) $baris['sampai']) - ((int) $baris['dari']) + 1;

                    if ($jumlah > 100) {
                        $validator->errors()->add(
                            "lantai.{$index}.sampai",
                            'Maksimal 100 kamar per baris rentang lantai.'
                        );
                    }
                }
            },
        ];
    }
}
