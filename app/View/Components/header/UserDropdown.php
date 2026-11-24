<?php

declare(strict_types=1);

namespace App\View\Components\header;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Menú de la cuenta en el encabezado: perfil, ayuda y cerrar sesión. */
class UserDropdown extends Component
{
    public function render(): View
    {
        return view('components.header.user-dropdown');
    }
}
