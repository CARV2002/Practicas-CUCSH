<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Support\TextoAviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notificaciones: bandeja completa, resumen para la campana, abrir (marca como leída) y marcar todas.
 */
class NotificacionController extends Controller
{
    /** Días tras los que una notificación ya leída se archiva (sale de la campana y de «Recientes»). */
    public const DIAS_ARCHIVO = 7;

    /**
     * Listado completo. «Recientes»: sin leer y leídas en la última semana. «Archivadas»: las leídas hace más tiempo,
     * que se conservan pero solo se ven aquí.
     */
    public function index(Request $request): View
    {
        $archivadas = $request->string('ver')->toString() === 'archivadas';
        $limite = now()->subDays(self::DIAS_ARCHIVO);
        $base = $request->user()->notifications();

        return view('notificaciones.index', [
            'archivadas' => $archivadas,
            'totalArchivadas' => (clone $base)->whereNotNull('read_at')->where('read_at', '<', $limite)->count(),
            'notificaciones' => ($archivadas
                ? $base->whereNotNull('read_at')->where('read_at', '<', $limite)
                : $base->where(fn ($q) => $q->whereNull('read_at')->orWhere('read_at', '>=', $limite)))
                ->paginate(20)->withQueryString(),
        ]);
    }

    /** Datos del dropdown de la campana: sin leer y leídas recientes (las archivadas solo en «Todas»). */
    public function resumen(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'no_leidas' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->where(fn ($q) => $q->whereNull('read_at')->orWhere('read_at', '>=', now()->subDays(self::DIAS_ARCHIVO)))
                ->limit(8)->get()->map(function ($n) {
                    $partes = TextoAviso::partes($n->data['mensaje'] ?? null, $n->data['titulo'] ?? null);

                    return [
                        'id' => $n->id,
                        'titulo' => $partes['titulo'],
                        'cuerpo' => $partes['detalle'],
                        'detalle' => $n->data['detalle'] ?? $n->data['empresa'] ?? null,
                        'leida' => $n->read_at !== null,
                        'hace' => $n->created_at->diffForHumans(),
                        'url' => route('notificaciones.abrir', $n->id),
                    ];
                }),
        ]);
    }

    /** Marca como leída y lleva al recurso relacionado. */
    public function abrir(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $url = $n->data['url'] ?? null;

        // Solo rutas internas: nunca se redirige a un dominio externo
        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return redirect($url);
        }

        // Avisos anteriores a `url`
        $convenioId = $n->data['convenio_id'] ?? null;

        if ($convenioId && $request->user()->hasAnyRole(Rol::deVinculacion())) {
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
