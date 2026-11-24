<?php

declare(strict_types=1);

namespace App\View\Components\common;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Cuadrícula decorativa de fondo (esquinas) en las pantallas de acceso. */
class CommonGridShape extends Component
{
    public function render(): View
    {
        return view('components.common.common-grid-shape');
    }
}
