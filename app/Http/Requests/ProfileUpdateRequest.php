<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Changer l'adresse, c'est choisir ou arrive le lien de
            // reinitialisation. Sans le mot de passe actuel, une session
            // volee quelques minutes suffisait a prendre le compte pour de bon.
            'current_password' => [
                Rule::requiredIf(fn () => mb_strtolower((string) $this->input('email')) !== $this->user()->email),
                'nullable',
                'current_password',
            ],
        ];
    }
}
