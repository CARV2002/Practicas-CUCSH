<?php

namespace App\Http\Requests\Auth;

use App\Enums\TipoOrganismo;
use App\Models\User;
use App\Rules\Rfc;
use App\Support\Formatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Etapa 1 del procedimiento de la empresa: SOLO la cuenta (organización, RFC, persona de contacto y acceso).
 * Los datos fiscales, legales y el expediente se capturan después en «Mi convenio», con borrador,
 * para que la empresa no pierda su avance si le falta un dato notarial.
 */
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
            'rfc' => Rfc::normalizar($this->input('rfc')),
            'contacto_telefono' => Formatos::digitos($this->input('contacto_telefono')) ?: null,
            'name' => Formatos::limpiar($this->input('name')),
            'razon_social' => Formatos::limpiar($this->input('razon_social')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // La organización
            'razon_social' => ['required', 'string', 'min:3', 'max:' . Formatos::MAX_RAZON_SOCIAL],
            'rfc' => ['required', 'string', new Rfc(), Rule::unique('empresas', 'rfc')->whereNull('deleted_at')],
            'tipo_organismo' => ['required', Rule::enum(TipoOrganismo::class)],
            'tipo_organismo_otro' => ['required_if:tipo_organismo,otro', 'nullable', 'string', 'min:3', 'max:80'],

            // Enlace de la organización con el CUCSH (quien administra la cuenta)
            'name' => ['required', 'string', 'min:5', 'max:' . Formatos::MAX_NOMBRE_PERSONA, 'regex:' . Formatos::NOMBRE_PERSONA],
            'contacto_cargo' => ['required', 'string', 'min:3', 'max:120', 'regex:' . Formatos::TEXTO_LETRAS],
            'contacto_telefono' => ['required', 'regex:' . Formatos::TELEFONO],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],

            'aviso_privacidad' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del contacto',
            'contacto_cargo' => 'cargo del contacto',
            'contacto_telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'razon_social' => 'razón social',
            'rfc' => 'RFC',
            'tipo_organismo' => 'tipo de organismo',
            'tipo_organismo_otro' => 'qué tipo de organismo es',
            'aviso_privacidad' => 'aviso de privacidad',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Falta :attribute: este campo es obligatorio.',
            'max' => '«:attribute» no puede pasar de :max caracteres.',
            'min' => '«:attribute» es muy corto: escribe al menos :min caracteres.',
            'email.email' => 'Escribe un correo válido, por ejemplo nombre@dominio.com.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.',
            'rfc.unique' => 'Ya hay una organización registrada con ese RFC. Si es la tuya, inicia sesión o escribe a Vinculación.',
            'tipo_organismo.enum' => 'Selecciona un tipo de organismo válido.',
            'tipo_organismo_otro.required_if' => 'Escribe qué tipo de organismo es (por ejemplo, organismo internacional).',
            'password.confirmed' => 'Las contraseñas no coinciden. Escribe la misma en ambos campos.',
            'aviso_privacidad.accepted' => 'Debes aceptar el aviso de privacidad para registrarte.',
            ...Formatos::mensajes(),
        ];
    }
}
