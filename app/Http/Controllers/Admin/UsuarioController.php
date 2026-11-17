<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /** Cuentas del sistema con su rol y, para el personal, su alcance. */
    public function index(Request $request): View
    {
        $usuarios = User::with(['roles', 'personal.carrera', 'personal.division'])
            ->when($request->filled('q'), fn($q) => $q->where(fn($w) => $w
                ->where('name', 'like', '%' . $request->string('q') . '%')
                ->orWhere('email', 'like', '%' . $request->string('q') . '%')))
            ->when($request->filled('rol'), fn($q) => $q->role($request->string('rol')->toString()))
            ->when($request->string('estado')->toString() === 'activo', fn($q) => $q->where('activo', true))
            ->when($request->string('estado')->toString() === 'inactivo', fn($q) => $q->where('activo', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.lista-usuarios', [
            'usuarios' => $usuarios,
            'roles' => Rol::cases(),
            'rolesAlta' => $this->rolesQuePuedeCrear($request->user()),
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'divisiones' => Division::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Alta de personal. Vinculación solo da de alta coordinadores (con su carrera o división);
     * el administrador técnico puede crear también Vinculación y otros administradores.
     * Alumnos y empresas se registran solos.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'rol' => $request->input('rol', Rol::Coordinador->value),
        ]);

        $permitidos = array_map(fn(Rol $r) => $r->value, $this->rolesQuePuedeCrear($request->user()));
        $esCoordinador = $request->input('rol') === Rol::Coordinador->value;

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'puesto' => ['nullable', 'string', 'max:255'],
            'rol' => ['required', Rule::in($permitidos)],
            // Un coordinador SIEMPRE tiene alcance: carrera o división (no ambas)
            'carrera_id' => [Rule::requiredIf($esCoordinador && ! $request->filled('division_id')), 'nullable', 'integer', Rule::exists('carreras', 'id'), 'prohibits:division_id'],
            'division_id' => [Rule::requiredIf($esCoordinador && ! $request->filled('carrera_id')), 'nullable', 'integer', Rule::exists('divisiones', 'id')],
        ], [
            'rol.in' => 'No puedes dar de alta cuentas con ese rol.',
            'carrera_id.required' => 'Elige la carrera (o la división) que coordinará.',
            'division_id.required' => 'Elige la carrera (o la división) que coordinará.',
            'carrera_id.prohibits' => 'Elige carrera o división, no ambas.',
        ]);

        $rol = Rol::from($datos['rol']);

        DB::transaction(function () use ($datos, $rol, $esCoordinador) {
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
            ]);

            $user->markEmailAsVerified();
            $user->assignRole($rol->value);

            \App\Services\Auditoria::registrar('usuario.rol_asignado', "Rol «{$rol->value}» asignado a {$user->email}", $user);

            Personal::create([
                'user_id' => $user->id,
                // Vinculación y administrador: alcance global; coordinador: su carrera o división
                'carrera_id' => $esCoordinador ? ($datos['carrera_id'] ?? null) : null,
                'division_id' => $esCoordinador ? ($datos['division_id'] ?? null) : null,
                'puesto' => $datos['puesto'] ?? $rol->label(),
            ]);
        });

        return back()->with('status', "Cuenta de {$rol->label()} creada.");
    }

    /** Activar o desactivar la cuenta. */
    public function toggleActivo(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }

        $this->autorizarSobre($request->user(), $usuario);

        $usuario->update(['activo' => !$usuario->activo]);

        return back()->with('status', $usuario->activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }

    /** Restablece la contraseña de una cuenta. */
    public function restablecerPassword(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id !== $request->user()->id) {
            $this->autorizarSobre($request->user(), $usuario);
        }

        $datos = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $usuario->update(['password' => $datos['password']]);

        return back()->with('status', 'Contraseña actualizada.');
    }

    public function verificarCorreo(Request $request, User $usuario): RedirectResponse
    {
        $this->autorizarSobre($request->user(), $usuario);

        if (!$usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }

        return back()->with('status', 'Correo marcado como verificado.');
    }

    /** @return Rol[] */
    private function rolesQuePuedeCrear(User $actor): array
    {
        return $actor->hasRole(Rol::Administrador->value)
            ? [Rol::Coordinador, Rol::Vinculacion, Rol::Administrador]
            : [Rol::Coordinador];
    }

    /** Evita escalar privilegios: Vinculación no toca cuentas de Vinculación ni del administrador técnico. */
    private function autorizarSobre(User $actor, User $usuario): void
    {
        if ($actor->hasRole(Rol::Administrador->value)) {
            return;
        }

        abort_if($usuario->hasAnyRole(Rol::deVinculacion()), 403, 'Solo el administrador técnico gestiona esa cuenta.');
    }
}
