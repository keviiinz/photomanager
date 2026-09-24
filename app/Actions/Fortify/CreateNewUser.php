<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'company_name' => ['nullable', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ], attributes: [
            'name' => __('nombre'),
            'company_name' => __('nombre de la empresa'),
            'email' => __('correo'),
            'password' => __('contraseña'),
        ])->validate();

        // Public sign-up is for photographers only; the role is never taken from the request,
        // so superadmin (or any other role) can't be self-assigned.
        return User::create([
            'name' => $input['name'],
            'company_name' => $input['company_name'] ?? null,
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => UserRole::Photographer,
        ]);
    }
}
