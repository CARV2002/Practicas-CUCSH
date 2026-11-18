<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    /** Grupos de acciones para las pestañas: prefijo de la clave de acción. */
    public const GRUPOS = [
        'acceso' => 'Accesos',
        'convenio' => 'Convenios',
        'documento' => 'Documentos',
        'empresa' => 'Empresas',
        'user' => 'Cuentas',
    ];

    public const FALLIDOS = ['acceso.fallido', 'acceso.bloqueado', 'acceso.cuenta_inactiva'];

    public function index(Request $request): View
    {
        $this->authorize('ver-auditoria');

        $grupo = $request->string('grupo')->toString();

        $logs = AuditLog::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('descripcion', 'like', $t)->orWhere('actor_nombre', 'like', $t));
            })
            ->when(isset(self::GRUPOS[$grupo]), fn ($q) => $q->where('accion', 'like', $grupo.'.%'))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->when($request->filled('entidad_tipo'), fn ($q) => $q->where('entidad_tipo', $request->string('entidad_tipo')))
            ->when($request->filled('entidad_id'), fn ($q) => $q->where('entidad_id', $request->integer('entidad_id')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $conteoGrupo = fn (string $g) => AuditLog::where('accion', 'like', $g.'.%')->count();

        return view('admin.auditoria', [
            'logs' => $logs,
            'acciones' => AuditLog::query()->distinct()->orderBy('accion')->pluck('accion'),
            'grupos' => collect(self::GRUPOS)->map(fn ($texto, $g) => ['texto' => $texto, 'conteo' => $conteoGrupo($g)]),
            'total' => AuditLog::count(),
            'resumen' => [
                'hoy' => AuditLog::whereDate('created_at', today())->count(),
                'personas_hoy' => AuditLog::whereDate('created_at', today())->whereNotNull('user_id')->distinct()->count('user_id'),
                'fallidos' => AuditLog::whereIn('accion', self::FALLIDOS)->where('created_at', '>=', now()->subDays(7))->count(),
                'semana' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }
}
