<?php

namespace App\Http\Requests;

use App\Models\Laundry;
use App\Models\Properti;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TetapkanPemilikRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->propertiAktif();
        $laundry = $this->route('laundry');

        return $user !== null
            && $properti !== null
            && $laundry instanceof Laundry
            && $laundry->properti_id === $properti->id
            && $user->can('tetapkanPemilik', $laundry);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $properti = $this->propertiAktif();

        return [
            'penghuni_id' => [
                'required',
                'integer',
                Rule::exists('penghuni', 'id')->where(
                    fn ($query) => $query->where('properti_id', $properti?->id)->whereNull('deleted_at')
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penghuni_id.required' => 'Penghuni tujuan belum dipilih.',
            'penghuni_id.integer' => 'Pilihan penghuni tidak valid.',
            'penghuni_id.exists' => 'Penghuni tidak ditemukan atau bukan milik properti ini.',
        ];
    }

    private function propertiAktif(): ?Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        return $properti instanceof Properti ? $properti : null;
    }
}
