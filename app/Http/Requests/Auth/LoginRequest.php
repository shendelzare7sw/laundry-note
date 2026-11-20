<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username belum diisi.',
            'username.max' => 'Username terlalu panjang (maksimal 100 karakter).',
            'password.required' => 'Password belum diisi.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $username = Str::lower(trim($this->string('username')->toString()));
        $user = User::query()->where('username', $username)->first();

        if ($user === null || ! Hash::check($this->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        if (! $user->aktif) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.inactive'),
            ]);
        }

        if ($user->role->isStafProperti()) {
            $properti = $user->properti;

            if ($properti === null || $properti->status !== 'aktif') {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'username' => trans('auth.property_inactive'),
                ]);
            }
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')->toString()).'|'.$this->ip());
    }
}
