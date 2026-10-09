<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('alumno')) {
            return redirect()->route('alumno.dashboard');
        }

        if ($user->hasRole('empresa')) {
            return redirect()->route('empresa.dashboard');
        }

        if ($user->hasRole('coordinador')) {
            return redirect()->route('coordinador.dashboard');
        }

        if ($user->hasRole('administrador')) {
            return redirect()->route('admin.dashboard');
        }
        
        abort(403);
    }
}