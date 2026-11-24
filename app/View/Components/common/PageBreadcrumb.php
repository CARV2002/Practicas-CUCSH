<?php

declare(strict_types=1);

namespace App\View\Components\common;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Título de la página con su ruta de migas (Inicio › Página). Recibe «pageTitle». */
class PageBreadcrumb extends Component
{
    public function render(): View
    {
        return view('components.common.page-breadcrumb');
    }
}
