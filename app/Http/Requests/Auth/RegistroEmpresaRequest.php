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
            'rfc' => Str::upper(preg_replace('/[\s-]+/', '', (string) $this->input('rfc'))),
            'contacto_telefono' => \App\Support\Formatos::digitos($this->input('contacto_telefono')) ?: null,
            'name' => \App\Support\Formatos::limpiar($this->input('name')),
            'representante_legal' => \App\Support\Formatos::limpiar($this->input('representante_legal')),
            'escritura_numero' => \App\Support\Formatos::digitos($this->input('escritura_numero')) ?: null,
            'folio_mercantil' => Str::upper(trim((string) $this->input('folio_mercantil'))) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Persona de contacto (quien administra la cuenta de la empresa)
            'name' => ['required', 'string', 'min:5', 'max:255', 'regex:' . \App\Support\Formatos::NOMBRE_PERSONA],
            'contacto_cargo' => ['nullable', 'string', 'max:255', 'regex:' . \App\Support\Formatos::TEXTO_LETRAS],
            'contacto_telefono' => ['nullable', 'regex:' . \App\Support\Formatos::TELEFONO],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],

            // Identidad legal: son los datos que el convenio necesita, por eso se piden bien escritos.
            'razon_social' => ['required', 'string', 'min:3', 'max:255'],
            // RFC de persona moral (12) o física (13): 3-4 letras, 6 dígitos de fecha, 3 de homoclave.
            'rfc' => ['required', 'string', 'regex:' . \App\Support\Formatos::RFC, Rule::unique('empresas', 'rfc')],
            'tipo_organismo' => ['required', Rule::enum(TipoOrganismo::class)],
            // Quien firma el convenio por parte de la empresa
            'representante_legal' => ['required', 'string', 'min:5', 'max:255', 'regex:' . \App\Support\Formatos::NOMBRE_PERSONA],
            // Declaraciones de la ENTIDAD del Formato de PP (van tal cual al convenio)
            'representante_cargo' => ['required', 'string', 'min:3', 'max:255', 'regex:' . \App\Support\Formatos::TEXTO_LETRAS],
            'domicilio_fiscal' => ['required', 'string', 'min:10', 'max:500'],
            // Datos notariales (escritura) o ley/decreto de creación, según el tipo de organismo
            ...\App\Support\DatosNotariales::reglas(),
            'objeto_social' => ['required', 'string', 'min:20', 'max:1500'],
            'facultad_firmante' => ['required', 'string', 'min:20', 'max:1500'],

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
            'representante_legal' => 'representante legal',
            'representante_cargo' => 'cargo del representante',
            'domicilio_fiscal' => 'domicilio legal',
            'constitucion' => 'ley o decreto de creación',
            'escritura_numero' => 'número de escritura',
            'escritura_fecha' => 'fecha de la escritura',
            'notario_nombre' => 'notario',
            'notaria_numero' => 'número de notaría',
            'notaria_ciudad' => 'ciudad de la notaría',
            'folio_mercantil' => 'folio mercantil',
            'objeto_social' => 'objeto social o atribuciones',
            'facultad_firmante' => 'facultad de quien firma',
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
            'rfc.regex' => 'El RFC no tiene un formato válido (12 caracteres para persona moral, 13 para persona física).',
            'rfc.unique' => 'Esa empresa ya está registrada (RFC duplicado).',
            'tipo_organismo.enum' => 'Selecciona un tipo de organismo válido.',
            'password.confirmed' => 'Las contraseñas no coinciden. Escribe la misma en ambos campos.',
            'aviso_privacidad.accepted' => 'Debes aceptar el aviso de privacidad para registrarte.',
            ...\App\Support\DatosNotariales::mensajes(),
            ...\App\Support\Formatos::mensajes(),
            'min' => '«:attribute» es muy corto: escribe al menos :min caracteres.',
        ];
    }
}
