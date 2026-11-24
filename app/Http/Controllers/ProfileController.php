<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Enums\ValidacionEstado;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Carrera;
use App\Support\Requisitos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * «Mi perfil»: datos de la cuenta, contraseña y, según el rol, los de la organización o los escolares del alumno.
 */
class ProfileController extends Controller
{
    /**
     * «Mi perfil»: datos de la cuenta y, según el rol, los de la organización o del alumno.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $rol = $user->rolPrincipal();
        $datos = ['user' => $user, 'rol' => $rol];

        if ($rol === Rol::Alumno && $user->alumno) {
            $alumno = $user->alumno->load('carrera');
            $imss = $alumno->documentoVigente('constancia_imss');
            $estadoImss = $imss?->getCustomProperty('estado');

            $datos['alumno'] = $alumno;
            $datos['requisitos'] = [
                ['texto' => 'Grado y turno capturados', 'ok' => $alumno->grado !== null && $alumno->turno !== null,
                    'ayuda' => 'Aparecen en tu carta de presentación.', 'seccion' => 'escolar'],
                ['texto' => 'Constancia IMSS subida', 'ok' => $imss !== null,
                    'ayuda' => 'Súbela en «Mis documentos».', 'ruta' => 'alumno.mis-documentos'],
                ['texto' => 'Constancia IMSS validada', 'ok' => $estadoImss === ValidacionEstado::Validada->value,
                    'ayuda' => $estadoImss === ValidacionEstado::Rechazada->value ? 'Fue rechazada: sube una nueva.' : 'Tu coordinación la revisa.', 'ruta' => 'alumno.mis-documentos'],
                ['texto' => 'Seguro IMSS vigente', 'ok' => $alumno->tieneImssVigenteHasta(now()),
                    'ayuda' => $alumno->vigencia_imss ? 'Vigente hasta el '.$alumno->vigencia_imss->format('d/m/Y').'.' : 'Captura la vigencia al subir tu constancia.', 'ruta' => 'alumno.mis-documentos'],
            ];
            $datos['motivoPostular'] = Requisitos::alumnoPostularse($alumno);
        }

        if ($rol === Rol::Empresa && $user->empresa) {
            $empresa = $user->empresa;
            $convenios = $empresa->convenios()->latest('id')->get();
            $datos['empresa'] = $empresa;
            $datos['convenioVigente'] = $convenios->first(fn ($c) => $c->estaVigente());
            $datos['convenioEnTramite'] = $convenios->first(fn ($c) => ! $c->estado->esFinal());
            $datos['expedienteCompleto'] = $empresa->expedienteEnOrden();
            $datos['representanteBloqueado'] = $empresa->documentosBloqueados();
        }

        if ($rol?->esPersonal() && $user->personal) {
            $personal = $user->personal->load(['carrera', 'division']);
            $datos['personal'] = $personal;
            if ($rol === Rol::Coordinador) {
                $datos['carreras'] = Carrera::enAlcance($personal)->with('division')->orderBy('nombre')->get();
            }
        }

        return view('profile.edit', $datos);
    }

    /**
     * Guarda nombre y correo; si cambia el correo, hay que verificarlo de nuevo.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $correoCambio = $user->isDirty('email');

        if ($correoCambio) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($correoCambio) {
            $user->sendEmailVerificationNotification();
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function actualizarFoto(Request $request): RedirectResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
        ], [
            'foto.required' => 'Selecciona una imagen.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'foto.max' => 'La foto no debe pesar más de 2 MB.',
            'foto.dimensions' => 'La foto debe medir al menos 100 × 100 píxeles.',
        ]);

        $user = $request->user();
        $user->addMediaFromRequest('foto')->toMediaCollection('foto');
        $user->forceFill(['foto_actualizada_at' => now()])->saveQuietly();

        return back()->with('status', 'foto-updated');
    }

    public function eliminarFoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->clearMediaCollection('foto');
        $user->forceFill(['foto_actualizada_at' => null])->saveQuietly();

        return back()->with('status', 'foto-deleted');
    }
}
