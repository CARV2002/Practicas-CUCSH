<?php

namespace App\Notifications;

use App\Models\Convenio;
use Illuminate\Notifications\Messages\MailMessage;

/** A la Coordinación de Prácticas: llegó una solicitud de convenio nueva (campana + correo). */
class NuevaSolicitudConvenio extends AvisoPracticas
{
    public function __construct(public Convenio $convenio)
    {
    }

    protected function porCorreo(): bool
    {
        return true;
    }

    protected function texto(): string
    {
        return "La empresa {$this->convenio->empresa->razon_social} envió una solicitud de convenio. Revisa su documentación.";
    }

    protected function ruta(): string
    {
        return route('admin.convenios.show', $this->convenio, false);
    }

    protected function detalle(): ?string
    {
        return $this->convenio->empresa->razon_social;
    }

    protected function asunto(): string
    {
        return "Nueva solicitud de convenio: {$this->convenio->empresa->razon_social}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->convenio->loadMissing(['empresa.user', 'dictamenes.carrera']);
        $e = $c->empresa;

        $contacto = collect([$e->contacto_nombre, $e->contacto_cargo, $e->contacto_telefono, $e->user?->email])
            ->filter()->join(' · ');

        $mensaje = (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Hola '.$notifiable->name)
            ->line("La empresa **{$e->razon_social}** ({$e->rfc}, {$e->tipo_organismo->label()}) envió una solicitud de convenio.")
            ->line("**Contacto:** {$contacto}")
            ->line('**Carreras de interés:** '.$c->dictamenes->map(fn ($d) => $d->carrera->nombre)->join('; '))
            ->line("**Plazas estimadas:** {$c->plazas_estimadas}");

        if (! $e->expedienteCompleto()) {
            $mensaje->line('Su expediente aún está incompleto: recibirás otro aviso cuando suba documentos.');
        }

        return $mensaje
            ->action('Revisar solicitud', url($this->ruta()))
            ->salutation('Coordinación de Prácticas Profesionales');
    }

    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'convenio_id' => $this->convenio->id,
            'empresa' => $this->convenio->empresa->razon_social,
        ];
    }
}