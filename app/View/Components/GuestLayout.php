<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Layout de las pantallas sin sesión (<x-guest-layout>): acceso, registro y recuperación de contraseña. */
class GuestLayout extends Component
{
    public function render(): View
    {
        return view('layouts.guest');
    }
}
