<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Rol;
use App\Listeners\RegistrarAccesos;
use App\Models\Convenio;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Policies\ConvenioPolicy;
use App\Policies\MediaPolicy;
use App\Policies\PostulacionPolicy;
use App\Policies\PracticaPolicy;
use App\Policies\ProgramaPolicy;
use App\Policies\ReportePolicy;
use App\Services\Auditoria;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Arranque de la aplicación: policies y permisos (ver-auditoria, operar-convenios), correo de verificación en español,
 * bitácora de archivos subidos o eliminados y, en local, todos los correos a una sola bandeja.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // En local, todos los correos llegan a una sola bandeja (las cuentas de prueba no existen)
        if (app()->environment('local') && filled(config('practicas.correo_redirigir_a'))) {
            Mail::alwaysTo(config('practicas.correo_redirigir_a'));
        }

        $this->fechasLegibles();

        Gate::policy(Convenio::class, ConvenioPolicy::class);
        Gate::policy(Programa::class, ProgramaPolicy::class);
        Gate::policy(Postulacion::class, PostulacionPolicy::class);
        Gate::policy(Practica::class, PracticaPolicy::class);
        Gate::policy(Reporte::class, ReportePolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);   // Spatie: no se autodescubre

        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Confirma tu correo · Prácticas Profesionales CUCSH')
            ->greeting('Hola '.$notifiable->name)
            ->line('Confirma tu correo para activar tu cuenta en la plataforma de Prácticas Profesionales del CUCSH.')
            ->action('Confirmar correo', $url)
            ->line('El enlace caduca en 60 minutos. Si no creaste esta cuenta, ignora este mensaje.')
            ->salutation('Prácticas Profesionales · CUCSH'));

        Media::created(function (Media $m) {
            Auditoria::registrar(
                'documento.subido',
                "Archivo «{$m->file_name}» subido a «{$m->collection_name}» de ".class_basename($m->model_type)." #{$m->model_id}",
                cambios: ['coleccion' => $m->collection_name, 'archivo' => $m->file_name, 'bytes' => $m->size],
                tipo: class_basename($m->model_type),
                id: (int) $m->model_id,
            );
        });

        Media::deleted(function (Media $m) {
            Auditoria::registrar(
                'documento.eliminado',
                "Archivo «{$m->file_name}» eliminado de «{$m->collection_name}» de ".class_basename($m->model_type)." #{$m->model_id}",
                cambios: ['coleccion' => $m->collection_name, 'archivo' => $m->file_name],
                tipo: class_basename($m->model_type),
                id: (int) $m->model_id,
            );
        });

        Event::subscribe(RegistrarAccesos::class);

        // Auditoría: solo el administrador del sistema
        Gate::define('ver-auditoria', fn (User $u) => $u->hasRole(Rol::Administrador->value)
            && $u->personal?->tieneAlcanceGlobal() === true);

        // Operar convenios, empresas y documentos físicos: solo Vinculación (el administrador supervisa en solo lectura)
        Gate::define('operar-convenios', fn (User $u) => $u->hasRole(Rol::Vinculacion->value) && $u->personal !== null);
    }

    /**
     * Las reglas de fecha (after, before…) dicen «hoy», «del 20/11/2026» o «la fecha de «Inicio»» en lugar de
     * «today», «2026-11-20» o el nombre técnico del otro campo: mensajes cortos que cualquiera entiende.
     */
    private function fechasLegibles(): void
    {
        foreach (['after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals'] as $regla) {
            Validator::replacer($regla, function (string $mensaje, string $atributo, string $r, array $parametros, $validador) {
                $p = (string) ($parametros[0] ?? '');
                $fecha = match (true) {
                    $p === 'today' => 'hoy',
                    $p === 'tomorrow' => 'mañana',
                    $p === 'yesterday' => 'ayer',
                    preg_match('/^\d{4}-\d{2}-\d{2}/', $p) === 1 => 'el '.Carbon::parse($p)->format('d/m/Y'),
                    // Otro campo: «Fecha de inicio» tal cual; si su nombre no dice «fecha», se antepone
                    default => self::fechaDeCampo((string) $validador->getDisplayableAttribute($p)),
                };

                return str_replace(['de el ', ' a el '], ['del ', ' al '], str_replace(':date', $fecha, $mensaje));
            });
        }
    }

    private static function fechaDeCampo(string $nombre): string
    {
        return str_starts_with(mb_strtolower($nombre), 'fecha') ? '«'.mb_ucfirst($nombre).'»' : 'la fecha de «'.$nombre.'»';
    }
}
