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
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
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
