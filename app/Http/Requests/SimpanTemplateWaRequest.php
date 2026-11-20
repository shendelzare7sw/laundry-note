<?php

namespace App\Http\Requests;

use App\Models\Pengaturan;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;

class SimpanTemplateWaRequest extends FormRequest
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
            'template_wa_tagihan' => ['required', 'string', 'max:4000'],
            'template_wa_peringatan' => ['required', 'string', 'max:4000'],
            'template_wa_rekap' => ['required', 'string', 'max:4000'],
        ];
    }
}
