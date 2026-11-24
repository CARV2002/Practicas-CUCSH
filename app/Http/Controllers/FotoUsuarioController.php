<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Foto de perfil (disco privado): la ve el dueño, el administrador y quien trata con esa persona en el proceso
 * (su coordinación, Vinculación con el personal y las organizaciones, la empresa con sus postulantes).
 */
class FotoUsuarioController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        abort_unless($this->puedeVer($request->user(), $user), 403);

        $media = $user->getFirstMedia('foto');
        abort_if($media === null, 404);

        $ruta = $media->hasGeneratedConversion('avatar') ? $media->getPath('avatar') : $media->getPath();
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function puedeVer(User $viewer, User $dueno): bool
    {
        if ($viewer->is($dueno) || $viewer->hasRole(Rol::Administrador->value)) {
            return true;
        }

        // Vinculación trata con empresas y personal, no con alumnos
        if ($viewer->hasRole(Rol::Vinculacion->value)) {
            return $dueno->alumno === null;
        }

        // La coordinación ve a los alumnos de su carrera/división
        if ($viewer->hasRole(Rol::Coordinador->value) && $viewer->personal && $dueno->alumno) {
            return $viewer->personal->puedeVerCarrera((int) $dueno->alumno->carrera_id);
        }

        // La empresa solo ve a los alumnos que se postularon a sus programas.
        if ($viewer->hasRole(Rol::Empresa->value) && $viewer->empresa && $dueno->alumno) {
            return Postulacion::where('alumno_id', $dueno->alumno->id)
                ->whereHas('programa.convenio', fn ($c) => $c->where('empresa_id', $viewer->empresa->id))
                ->exists();
        }

        return false;
    }
}
