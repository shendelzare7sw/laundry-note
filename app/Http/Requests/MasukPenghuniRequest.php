<?php

namespace App\Http\Requests;

use App\Models\Penghuni;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasukPenghuniRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->propertiAktif();

        return $user !== null
            && $properti !== null
            && $user->can('kelolaKontrak', [Penghuni::class, $properti]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $properti = $this->propertiAktif();

        return [
            'nama' => ['required', 'string', 'max:120'],
            'alias' => ['nullable', 'string', 'max:120'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'kamar_id' => [
                'required',
                'integer',
                Rule::exists('kamar', 'id')->where(fn ($query) => $query->where('properti_id', $properti?->id)),
            ],
            'tanggal_masuk' => ['required', 'date'],
            'tanggal_masuk_perkiraan' => ['sometimes', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function propertiAktif(): ?Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        return $properti instanceof Properti ? $properti : null;
    }
}
