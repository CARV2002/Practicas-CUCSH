<?php

namespace App\Http\Controllers\Alumno;

use App\Enums\Dictamen;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado de convenios vigentes por carrera: reemplaza la lista que Vinculación enviaba por correo.
 * Por defecto muestra los de la carrera del alumno; puede consultar otras.
 */
class ConvenioVigenteController extends Controller
{
    public function index(Request $request): View
    {
        $alumno = $request->user()->alumno->load('carrera');
        $carreraId = $request->filled('carrera_id') ? $request->integer('carrera_id') : (int) $alumno->carrera_id;

        $convenios = Convenio::vigentes()
            ->whereHas('empresa.user', fn($u) => $u->where('activo', true))
            ->whereHas('dictamenes', fn($d) => $d->where('dictamen', Dictamen::Aprobatorio->value)
                ->when($carreraId > 0, fn($q) => $q->where('carrera_id', $carreraId)))
            ->with([
                'empresa',
                'dictamenes' => fn($d) => $d->where('dictamen', Dictamen::Aprobatorio->value)->with('carrera'),
            ])
            ->when($request->filled('q'), fn($q) => $q->whereHas('empresa', fn($e) => $e
                ->where('razon_social', 'like', '%' . $request->string('q') . '%')))
            ->orderBy('fecha_fin_vigencia')
            ->paginate(15)
            ->withQueryString();

        // Vacantes abiertas de cada convenio para la carrera consultada
        $vacantes = Programa::abiertos()
            ->whereIn('convenio_id', $convenios->pluck('id'))
            ->when($carreraId > 0, fn($q) => $q->where('carrera_id', $carreraId))
            ->selectRaw('convenio_id, count(*) as total')
            ->groupBy('convenio_id')
            ->pluck('total', 'convenio_id');

        return view('alumno.convenios-vigentes', [
            'alumno' => $alumno,
            'convenios' => $convenios,
            'vacantes' => $vacantes,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'carreraId' => $carreraId,
        ]);
    }
}
