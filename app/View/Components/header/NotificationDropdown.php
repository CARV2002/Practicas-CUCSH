<?php

declare(strict_types=1);

namespace App\View\Components\header;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Campana de notificaciones del encabezado (lógica en resources/js/alpine/notificaciones.js). */
class NotificationDropdown extends Component
{
    public function render(): View
    {
        return view('components.header.notification-dropdown');
    }
}
