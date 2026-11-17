<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistroAlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'num_control' => trim((string) $this->input('num_control')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'carrera_id' => ['required', 'integer', Rule::exists('carreras', 'id')],
            'num_control' => ['required', 'string', 'max:20', Rule::unique('alumnos', 'num_control')],
            'grado' => ['required', 'integer', 'between:1,12'],
            'turno' => ['required', Rule::enum(\App\Enums\Turno::class)],
            'aviso_privacidad' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'carrera_id' => 'carrera',
            'num_control' => 'código de alumno',
            'grado' => 'grado',
            'turno' => 'turno',
            'aviso_privacidad' => 'aviso de privacidad',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Falta :attribute: este campo es obligatorio.',
            'email.email' => 'Escribe un correo válido, por ejemplo nombre@dominio.com.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.',
            'num_control.unique' => 'Ya existe una cuenta con ese código de alumno.',
            'carrera_id.exists' => 'Selecciona una carrera válida.',
            'password.confirmed' => 'Las contraseñas no coinciden. Escribe la misma en ambos campos.',
            'aviso_privacidad.accepted' => 'Debes aceptar el aviso de privacidad para registrarte.',
        ];
    }
}
