<?php

declare(strict_types=1);

namespace App\View\Components\Alumno;

use App\Models\Carrera;
use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * «Tu coordinación»: el alumno resuelve su proceso (postulación, cartas, reportes, liberación)
 * con la Coordinación de su carrera. Vinculación solo atiende convenios con empresas.
 */
class TuCoordinacion extends Component
{
    public ?User $coordinador;

    /** Correo de la coordinación o, si no lo ha capturado, el de su coordinador. */
    public ?string $correo;

    public ?string $telefono;

    public function __construct(public Carrera $carrera)
    {
        $this->coordinador = $carrera->coordinadores()->first();
        $this->correo = $carrera->coordinacion_correo ?: $this->coordinador?->email;
        $this->telefono = $carrera->coordinacion_telefono;
    }

    public function render(): View
    {
        return view('components.alumno.tu-coordinacion');
    }
}
