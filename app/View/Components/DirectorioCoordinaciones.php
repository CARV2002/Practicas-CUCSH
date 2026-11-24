<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Carrera;
use App\Models\Division;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Directorio público de coordinaciones de carrera (páginas de Estudiantes y Contacto): el alumno resuelve su
 * trámite con la Coordinación de su carrera. Cada coordinación captura su correo y teléfono en «Reglas de mi carrera».
 */
class DirectorioCoordinaciones extends Component
{
    /** @var Collection<int, Division> divisiones con sus carreras, en orden alfabético */
    public Collection $divisiones;

    public int $totalCarreras;

    /** @var list<string> texto de búsqueda de cada carrera (para el buscador en Alpine) */
    public array $textosCarreras;

    public function __construct(public string $titulo = 'Directorio de coordinaciones de carrera')
    {
        $this->divisiones = Division::with(['carreras' => fn ($q) => $q->orderBy('nombre')])
            ->whereHas('carreras')
            ->orderBy('nombre')
            ->get();

        $this->totalCarreras = $this->divisiones->sum(fn (Division $d) => $d->carreras->count());
        $this->textosCarreras = $this->divisiones
            ->flatMap(fn (Division $d) => $d->carreras->map(fn (Carrera $c) => $this->textoBusqueda($c, $d)))
            ->values()
            ->all();
    }

    /** Nombre, clave y división en minúsculas y sin acentos: «derecho» encuentra «Derecho» y «DÉRECHO». */
    public function textoBusqueda(Carrera $carrera, Division $division): string
    {
        return mb_strtolower(Str::ascii($carrera->nombre.' '.$carrera->carrera_clave.' '.$division->nombre));
    }

    public function render(): View
    {
        return view('components.directorio-coordinaciones');
    }
}
