<?php

declare(strict_types=1);

namespace App\View\Components\common;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Pantalla de carga que se desvanece en cuanto Alpine termina de iniciar la página. */
class Preloader extends Component
{
    public function render(): View
    {
        return view('components.common.preloader');
    }
}
