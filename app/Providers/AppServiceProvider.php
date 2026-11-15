<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\User;
use App\Models\Convenio;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Policies\ConvenioPolicy;
use App\Policies\MediaPolicy;
use App\Policies\PostulacionPolicy;
use App\Policies\PracticaPolicy;
use App\Policies\ProgramaPolicy;
use App\Policies\ReportePolicy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\Auditoria;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Convenio::class, ConvenioPolicy::class);
        Gate::policy(Programa::class, ProgramaPolicy::class);
        Gate::policy(Postulacion::class, PostulacionPolicy::class);
        Gate::policy(Practica::class, PracticaPolicy::class);
        Gate::policy(Reporte::class, ReportePolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);   // Spatie: no se autodescubre

        VerifyEmail::toMailUsing(fn($notifiable, string $url) => (new MailMessage)
            ->subject('Confirma tu correo · Prácticas Profesionales CUCSH')
            ->greeting('Hola ' . $notifiable->name)
            ->line('Confirma tu correo para activar tu cuenta en la plataforma de Prácticas Profesionales del CUCSH.')
            ->action('Confirmar correo', $url)
            ->line('El enlace caduca en 60 minutos. Si no creaste esta cuenta, ignora este mensaje.')
            ->salutation('Coordinación de Prácticas Profesionales'));

        Media::created(function (Media $m) {
            Auditoria::registrar(
                'documento.subido',
                "Archivo «{$m->file_name}» subido a «{$m->collection_name}» de " . class_basename($m->model_type) . " #{$m->model_id}",
                cambios: ['coleccion' => $m->collection_name, 'archivo' => $m->file_name, 'bytes' => $m->size],
                tipo: class_basename($m->model_type),
                id: (int) $m->model_id,
            );
        });

        Media::deleted(function (Media $m) {
            Auditoria::registrar(
                'documento.eliminado',
                "Archivo «{$m->file_name}» eliminado de «{$m->collection_name}» de " . class_basename($m->model_type) . " #{$m->model_id}",
                cambios: ['coleccion' => $m->collection_name, 'archivo' => $m->file_name],
                tipo: class_basename($m->model_type),
                id: (int) $m->model_id,
            );
        });

        Event::subscribe(\App\Listeners\RegistrarAccesos::class);

        Gate::define('ver-auditoria', fn(User $u) => $u->hasRole(Rol::Administrador->value)
            && $u->personal?->tieneAlcanceGlobal() === true);
    }
}