<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Http\Request;

class FotoUsuarioController extends Controller
{
    public function __invoke(Request $request, User $user)
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

        // La empresa solo ve a los alumnos que se postularon a sus programas.
        if ($viewer->hasRole(Rol::Empresa->value) && $viewer->empresa && $dueno->alumno) {
            return Postulacion::where('alumno_id', $dueno->alumno->id)
                ->whereHas('programa.convenio', fn ($c) => $c->where('empresa_id', $viewer->empresa->id))
                ->exists();
        }

        return false;
    }
}