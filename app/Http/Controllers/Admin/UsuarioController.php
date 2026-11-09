<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /** Alumnos, empresas y administradores con su rol. */
    public function index(Request $request): View
    {
        $usuarios = User::with('roles')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('rol'), fn ($q) => $q->role($request->string('rol')->toString()))
            ->when($request->string('estado')->toString() === 'activo', fn ($q) => $q->where('activo', true))
            ->when($request->string('estado')->toString() === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.lista-usuarios', [
            'usuarios' => $usuarios,
            'roles' => Rol::cases(),
        ]);
    }

    /** Alta del personal de la Coordinación (administrador). Alumnos y empresas se registran solos. */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'puesto' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);

        $user->markEmailAsVerified();
        $user->assignRole(Rol::Administrador->value);

        Personal::create([
            'user_id' => $user->id,
            'puesto' => $datos['puesto'] ?? 'Coordinación de Prácticas Profesionales',
        ]);

        return back()->with('status', 'Administrador creado.');
    }

    /** Activar o desactivar la cuenta. */
    public function toggleActivo(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with('status', $usuario->activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }

    /** Restablece la contraseña de una cuenta. */
    public function restablecerPassword(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $usuario->update(['password' => $datos['password']]);

        return back()->with('status', 'Contraseña actualizada.');
    }
}
