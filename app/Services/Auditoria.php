<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitácora de auditoría: registra acciones con su usuario, IP y cambios; sinRegistro() la pausa (seeders).
 */
class Auditoria
{
    private static bool $pausada = false;

    private const SENSIBLES = ['password', 'token', 'remember_token'];

    /** Ejecuta algo sin registrar (seeders). */
    public static function sinRegistro(callable $fn): mixed
    {
        $antes = self::$pausada;
        self::$pausada = true;

        try {
            return $fn();
        } finally {
            self::$pausada = $antes;
        }
    }

    /** Evento manual. Si no hay entidad como modelo, pasa $tipo e $id. */
    public static function registrar(
        string $accion,
        string $descripcion,
        ?Model $entidad = null,
        ?array $cambios = null,
        ?User $actor = null,
        ?string $tipo = null,
        ?int $id = null,
    ): void {
        if (self::$pausada || ! config('practicas.auditoria', true)) {
            return;
        }

        try {
            $actor ??= auth()->user();

            AuditLog::create([
                'user_id' => $actor?->id,
                'actor_nombre' => $actor?->name,
                'actor_rol' => $actor?->getRoleNames()->first(),
                'accion' => $accion,
                'entidad_tipo' => $entidad ? class_basename($entidad) : $tipo,
                'entidad_id' => $entidad?->getKey() ?? $id,
                'descripcion' => Str::limit($descripcion, 480),
                'cambios' => $cambios,
                'ip' => self::ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);   // la auditoría nunca debe tumbar la operación
        }
    }

    /** Evento de un modelo (lo usa el trait Auditable). */
    public static function modelo(Model $m, string $evento): void
    {
        // Solo los modelos con el trait Auditable traen su etiqueta y sus campos a omitir
        if (self::$pausada || ! config('practicas.auditoria', true) || ! method_exists($m, 'auditoriaEtiqueta')
            || ! method_exists($m, 'auditoriaOmitir') || ! method_exists($m, 'auditoriaNombre')) {
            return;
        }

        $omitir = ['updated_at', 'created_at', 'remember_token', ...$m->auditoriaOmitir()];
        $etiqueta = $m->auditoriaEtiqueta();
        $ref = "{$etiqueta} #{$m->getKey()}".($m->auditoriaNombre() ? " ({$m->auditoriaNombre()})" : '');
        $accion = Str::snake(Str::ascii($etiqueta)).".{$evento}";

        if ($evento === 'actualizado') {
            $cambios = [];

            foreach ($m->getChanges() as $campo => $nuevo) {
                if (in_array($campo, $omitir, true)) {
                    continue;
                }

                $cambios[$campo] = in_array($campo, self::SENSIBLES, true)
                    ? ['redactado' => true]
                    : ['antes' => self::val($m->getRawOriginal($campo)), 'despues' => self::val($nuevo)];
            }

            if ($cambios === []) {
                return;   // solo cambió updated_at
            }

            $descripcion = "{$ref}: ".self::resumen($cambios);
        } elseif ($evento === 'creado') {
            $nuevo = [];

            foreach ($m->getAttributes() as $campo => $valor) {
                if (! in_array($campo, $omitir, true)) {
                    $nuevo[$campo] = in_array($campo, self::SENSIBLES, true) ? '[redactado]' : self::val($valor);
                }
            }

            $cambios = ['nuevo' => $nuevo];
            $descripcion = "{$ref} creado";
        } else {
            $cambios = null;
            $descripcion = "{$ref} eliminado";
        }

        self::registrar($accion, $descripcion, $m, $cambios);
    }

    private static function resumen(array $cambios): string
    {
        $partes = [];

        foreach (array_slice($cambios, 0, 3, true) as $campo => $c) {
            $partes[] = isset($c['redactado'])
                ? "{$campo} cambió"
                : "{$campo}: ".self::corto($c['antes']).' → '.self::corto($c['despues']);
        }

        $extra = count($cambios) - 3;

        return implode('; ', $partes).($extra > 0 ? " (+{$extra} más)" : '');
    }

    private static function corto(mixed $v): string
    {
        if ($v === null) {
            return '—';
        }

        return Str::limit(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 50);
    }

    private static function val(mixed $v): mixed
    {
        return is_string($v) ? Str::limit($v, 300) : $v;
    }

    private static function ip(): ?string
    {
        return app()->runningInConsole() && ! app()->runningUnitTests() ? 'consola' : request()->ip();
    }
}
