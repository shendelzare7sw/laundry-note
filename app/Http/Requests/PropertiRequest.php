<?php

namespace App\Http\Requests;

use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $properti = $this->route('properti');

        if ($properti !== null) {
            return $user->can('update', $properti);
        }

        return $user->can('create', Properti::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'nama_properti_laporan' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(['draf', 'aktif', 'arsip'])],
        ];
    }
}
