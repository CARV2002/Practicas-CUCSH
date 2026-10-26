<?php

namespace App\Http\Requests\Auth;

use App\Enums\TipoOrganismo;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistroEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'rfc' => Str::upper(trim((string) $this->input('rfc'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Persona de contacto (quien administra la cuenta de la empresa)
            'name' => ['required', 'string', 'max:255'],
            'contacto_cargo' => ['nullable', 'string', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],

            // Identidad legal: son los datos que el convenio necesita, por eso se piden bien escritos.
            'razon_social' => ['required', 'string', 'max:255'],
            // RFC de persona moral (12) o física (13): 3-4 letras, 6 dígitos de fecha, 3 de homoclave.
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u', Rule::unique('empresas', 'rfc')],
            'tipo_organismo' => ['required', Rule::enum(TipoOrganismo::class)],

            'aviso_privacidad' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del contacto',
            'contacto_cargo' => 'cargo',
            'contacto_telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'razon_social' => 'razón social',
            'rfc' => 'RFC',
            'tipo_organismo' => 'tipo de organismo',
            'aviso_privacidad' => 'aviso de privacidad',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'rfc.regex' => 'El RFC no tiene un formato válido (12 caracteres para persona moral, 13 para persona física).',
            'rfc.unique' => 'Esa empresa ya está registrada (RFC duplicado).',
            'tipo_organismo.enum' => 'Selecciona un tipo de organismo válido.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'aviso_privacidad.accepted' => 'Debes aceptar el aviso de privacidad para registrarte.',
        ];
    }
}
