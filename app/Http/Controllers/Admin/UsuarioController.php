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
            'conteosRol' => collect(Rol::cases())->mapWithKeys(fn (Rol $r) => [$r->value => User::role($r->value)->count()]),
            'totalUsuarios' => User::count(),
            'inactivos' => User::where('activo', false)->count(),
            'sinVerificar' => User::whereNull('email_verified_at')->count(),
            'roles' => Rol::cases(),
            'rolesAlta' => $this->rolesQuePuedeCrear($request->user()),
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'divisiones' => Division::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Alta de personal (solo el administrador): coordinadores con su carrera o división,
     * la cuenta de Vinculación y otros administradores. Alumnos y empresas se registran solos.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'rol' => $request->input('rol', Rol::Coordinador->value),
        ]);

        $permitidos = array_map(fn(Rol $r) => $r->value, $this->rolesQuePuedeCrear($request->user()));
        $esCoordinador = $request->input('rol') === Rol::Coordinador->value;

        // Solo correos institucionales de la UdG (p. ej. @academicos.udg.mx o @csh.udg.mx)
        $dominios = config('practicas.google.dominios_personal');
        $datos = $request->validate([
            'name' => ['required', 'string', 'min:5', 'max:' . \App\Support\Formatos::MAX_NOMBRE_PERSONA, 'regex:' . \App\Support\Formatos::NOMBRE_PERSONA],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class),
                function ($atributo, $valor, $falla) use ($dominios) {
                    if (!in_array(Str::after((string) $valor, '@'), $dominios, true)) {
                        $falla('Usa su correo institucional: ' . collect($dominios)->map(fn ($d) => '@' . $d)->join(', ', ' o ') . '.');
                    }
                }],
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
            'name.regex' => 'Escribe nombre y apellido, solo con letras.',
        ]);

        $rol = Rol::from($datos['rol']);

        $user = DB::transaction(function () use ($datos, $rol, $esCoordinador) {
            // Contraseña aleatoria, encriptada e inaccesible: nadie (tampoco el administrador) la conoce.
            // El correo se verifica cuando la persona configura su contraseña desde su bandeja institucional.
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Str::random(64),
            ]);

            $user->assignRole($rol->value);

            \App\Services\Auditoria::registrar('usuario.rol_asignado', "Rol «{$rol->value}» asignado a {$user->email}", $user);

            $personal = Personal::create([
                'user_id' => $user->id,
                // Vinculación y administrador: alcance global; coordinador: su carrera o división
                'carrera_id' => $esCoordinador ? ($datos['carrera_id'] ?? null) : null,
                'division_id' => $esCoordinador ? ($datos['division_id'] ?? null) : null,
                'puesto' => $datos['puesto'] ?? $rol->label(),
            ]);

            // Una coordinación nueva hereda lo que ya estaba esperando en su carrera
            if ($esCoordinador) {
                $this->avisarPendientes($user, $personal);
            }

            return $user;
        });

        $enviado = $this->enviarEnlace($user, 'alta');

        return back()->with('status', $enviado
            ? "Cuenta de {$rol->label()} creada. Enviamos a {$user->email} el enlace para que configure su contraseña (vence en 72 horas)."
            : "Cuenta de {$rol->label()} creada, pero no se pudo enviar el correo. Usa «Enviar enlace de contraseña» cuando el correo funcione.");
    }

    /** Activar o desactivar la cuenta. */
    public function toggleActivo(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }

        $usuario->update(['activo' => !$usuario->activo]);

        return back()->with('status', $usuario->activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }

    /**
     * Reenvía el enlace «Configurar mi contraseña» (olvido o cuenta sin activar).
     * El administrador ya no escribe contraseñas: nunca conoce la clave de otra persona.
     */
    public function restablecerPassword(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'Para cambiar tu propia contraseña usa tu perfil.']);
        }

        return $this->enviarEnlace($usuario, 'restablecer')
            ? back()->with('status', "Enviamos a {$usuario->email} un enlace para configurar su contraseña (vence en 72 horas).")
            : back()->withErrors(['usuario' => 'No se pudo enviar el correo. Revisa la configuración del correo e inténtalo de nuevo.']);
    }

    /** Enlace único y temporal para que la persona ponga su propia contraseña. */
    private function enviarEnlace(User $usuario, string $motivo): bool
    {
        $token = \Illuminate\Support\Facades\Password::broker('invitaciones')->createToken($usuario);

        try {
            $usuario->notify(new \App\Notifications\ConfigurarContrasena($token, $motivo));
            \App\Services\Auditoria::registrar('usuario.enlace_contrasena', "Enlace para configurar contraseña enviado a {$usuario->email}", $usuario);

            return true;
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            report($e);

            return false;
        }
    }

    public function verificarCorreo(User $usuario): RedirectResponse
    {
        if (!$usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }

        return back()->with('status', 'Correo marcado como verificado.');
    }

    /** Resumen de lo que ya esperaba en la carrera o división de una coordinación recién creada. */
    private function avisarPendientes(User $user, Personal $personal): void
    {
        $partes = array_filter([
            ($n = \App\Models\Alumno::conExpedientePorRevisar($personal)->count()) ? "{$n} expediente(s) de alumnos (kardex e IMSS) por revisar" : null,
            ($n = \App\Models\Postulacion::visiblesPara($personal)->where('estado', \App\Enums\PostulacionEstado::Pendiente->value)->count()) ? "{$n} postulación(es) por validar" : null,
            ($n = \App\Models\Programa::whereIn('carrera_id', \App\Models\Carrera::enAlcance($personal)->select('id'))
                ->where('estado', \App\Enums\ProgramaEstado::PendienteAprobacion->value)->count()) ? "{$n} vacante(s) por aprobar" : null,
            ($n = \App\Models\ConvenioCarrera::visiblesPara($personal)->pendientes()
                ->whereHas('convenio', fn ($c) => $c->where('estado', \App\Enums\ConvenioEstado::EnValidacionAcademica->value))->count()) ? "{$n} dictamen(es) de convenio" : null,
        ]);

        if ($partes === []) {
            return;
        }

        \App\Services\Avisos::enviar($user, new \App\Notifications\AvisoOperativo(
            mensaje: 'Bienvenida a la plataforma. Tu carrera ya tenía pendientes: ' . implode(', ', $partes) . '.',
            url: route('coordinacion.dashboard', absolute: false),
        ));
    }

    /** @return Rol[] Roles de personal que se dan de alta aquí (el coordinador, primero: es el alta más común). */
    private function rolesQuePuedeCrear(User $actor): array
    {
        return [Rol::Coordinador, Rol::Vinculacion, Rol::Administrador];
    }
}
