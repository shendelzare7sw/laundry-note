<?php

namespace App\Http\Requests;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanPenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null || $actor->isPenjaga()) {
            return false;
        }

        $targetRole = Peran::tryFrom((string) $this->input('role'));

        if ($targetRole === null) {
            return true;
        }

        $propertiId = $this->filled('properti_id')
            ? (int) $this->input('properti_id')
            : $actor->properti_id;

        return Gate::allows('kelolaPeran', [User::class, $targetRole, $propertiId]);
    }

    protected function prepareForValidation(): void
    {
        $actor = $this->user();

        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
            'properti_id' => $actor?->isAdmin()
                ? $actor->properti_id
                : ($this->filled('properti_id') ? (int) $this->input('properti_id') : null),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $targetRole = Peran::tryFrom((string) $this->input('role'));

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^\S+$/', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Peran::class)],
            'properti_id' => [
                Rule::requiredIf(fn (): bool => $targetRole?->isStafProperti() ?? false),
                'nullable',
                'integer',
                Rule::exists('properti', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap pengguna belum diisi.',
            'name.max' => 'Nama lengkap pengguna maksimal 120 karakter.',
            'username.required' => 'Username belum diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.regex' => 'Username tidak boleh mengandung spasi (karakter unik seperti @, #, $, ., _, - diperbolehkan).',
            'username.unique' => 'Username tersebut sudah dipakai akun lain. Silakan gunakan username berbeda.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email tersebut sudah terdaftar di sistem.',
            'password.required' => 'Password awal belum diisi.',
            'password.min' => 'Password awal minimal 8 karakter.',
            'role.required' => 'Peran pengguna belum dipilih.',
            'role.enum' => 'Peran pengguna yang dipilih tidak valid.',
            'properti_id.required' => 'Properti penempatan wajib dipilih untuk staf properti.',
            'properti_id.exists' => 'Properti penempatan yang dipilih tidak valid.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $targetRole = Peran::tryFrom((string) $this->input('role'));
                $propertiId = $this->input('properti_id');

                if ($targetRole === Peran::Admin && $propertiId !== null) {
                    $sudahAdaAdmin = User::query()
                        ->where('properti_id', $propertiId)
                        ->where('role', Peran::Admin->value)
                        ->where('aktif', true)
                        ->exists();

                    if ($sudahAdaAdmin) {
                        $validator->errors()->add(
                            'role',
                            'Properti ini sudah memiliki admin aktif. Nonaktifkan admin lama terlebih dahulu.'
                        );
                    }
                }
            },
        ];
    }
}
