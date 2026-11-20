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

    protected function prepareForValidation(): void
    {
        $data = [];

        if (! $this->filled('nama_properti_laporan') && $this->filled('nama')) {
            $data['nama_properti_laporan'] = trim((string) $this->input('nama'));
        }

        if (! $this->filled('status') && $this->has('status_aktif')) {
            $data['status'] = $this->boolean('status_aktif') ? 'aktif' : 'arsip';
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'no_wa_admin' => ['nullable', 'string', 'max:30'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'nama_properti_laporan' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(['draf', 'aktif', 'arsip'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama properti belum diisi.',
            'nama.max' => 'Nama properti maksimal 120 karakter.',
            'nama_properti_laporan.required' => 'Nama properti di laporan belum diisi.',
            'nama_properti_laporan.max' => 'Nama properti di laporan maksimal 120 karakter.',
            'alamat.max' => 'Alamat properti maksimal 500 karakter.',
            'no_wa_admin.max' => 'Nomor WhatsApp admin maksimal 30 karakter.',
            'deskripsi.max' => 'Deskripsi properti maksimal 500 karakter.',
            'status.required' => 'Status properti belum dipilih.',
            'status.in' => 'Status properti yang dipilih tidak valid.',
        ];
    }
}
