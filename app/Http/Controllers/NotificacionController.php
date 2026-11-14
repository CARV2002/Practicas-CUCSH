<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    /** Listado completo de notificaciones del usuario. */
    public function index(Request $request): View
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Datos del dropdown de la campana: no leídas + últimas 8. */
    public function resumen(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'no_leidas' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(8)->get()->map(fn($n) => [
                'id' => $n->id,
                'mensaje' => $n->data['mensaje'] ?? 'Notificación',
                'detalle' => $n->data['detalle'] ?? $n->data['empresa'] ?? null,
                'leida' => $n->read_at !== null,
                'hace' => $n->created_at->diffForHumans(),
                'url' => route('notificaciones.abrir', $n->id),
            ]),
        ]);
    }

    /** Marca como leída y lleva al recurso relacionado. */
    public function abrir(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $url = $n->data['url'] ?? null;

        // Solo rutas internas: nunca se redirige a un dominio externo
        if (is_string($url) && str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')) {
            return redirect($url);
        }

        // Avisos anteriores a `url`
        $convenioId = $n->data['convenio_id'] ?? null;

        if ($convenioId && $request->user()->hasRole('administrador')) {
            return redirect()->route('admin.convenios.show', $convenioId);
        }

        if ($convenioId && $request->user()->hasRole('empresa')) {
            return redirect()->route('empresa.convenio');
        }

        return redirect()->route('notificaciones.index');
    }

    public function marcarTodas(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('status', 'Notificaciones marcadas como leídas.');
    }
}