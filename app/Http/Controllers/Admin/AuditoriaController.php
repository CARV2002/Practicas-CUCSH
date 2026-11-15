<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('ver-auditoria');

        $logs = AuditLog::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('descripcion', 'like', $t)->orWhere('actor_nombre', 'like', $t));
            })
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->when($request->filled('entidad_tipo'), fn ($q) => $q->where('entidad_tipo', $request->string('entidad_tipo')))
            ->when($request->filled('entidad_id'), fn ($q) => $q->where('entidad_id', $request->integer('entidad_id')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.auditoria', [
            'logs' => $logs,
            'acciones' => AuditLog::query()->distinct()->orderBy('accion')->pluck('accion'),
        ]);
    }
}