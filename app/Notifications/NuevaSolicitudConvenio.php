<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Convenio;
use Illuminate\Notifications\Messages\MailMessage;

/** A Vinculación: llegó una solicitud de convenio nueva (campana + correo). */
class NuevaSolicitudConvenio extends AvisoPracticas
{
    public function __construct(public Convenio $convenio) {}

    protected function porCorreo(): bool
    {
        return true;
    }

    protected function texto(): string
    {
        $empresa = $this->convenio->empresa->razon_social;
        $a = $this->convenio->antecedente();

        return match ($a['tipo'] ?? null) {
            'reenvio' => "{$empresa} volvió a enviar su solicitud de convenio. La anterior la rechazó {$a['origen']}".($a['motivo'] ? ": «{$a['motivo']}»" : '').'. Revisa que la nueva propuesta lo atienda.',
            'renovacion' => "{$empresa} envió la renovación de su convenio ".($a['anterior']->folio ?? '').'. Revisa su documentación.',
            default => "La empresa {$empresa} envió una solicitud de convenio. Revisa su documentación.",
        };
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
        return match ($this->convenio->antecedente()['tipo'] ?? null) {
            'reenvio' => "Reenvío de solicitud de convenio: {$this->convenio->empresa->razon_social}",
            'renovacion' => "Renovación de convenio: {$this->convenio->empresa->razon_social}",
            default => "Nueva solicitud de convenio: {$this->convenio->empresa->razon_social}",
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->convenio->loadMissing(['empresa.user', 'dictamenes.carrera']);
        $e = $c->empresa;

        $contacto = collect([$e->contacto_nombre, $e->contacto_cargo, $e->contacto_telefono, $e->user?->email])
            ->filter()->join(' · ');

        $mensaje = (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Hola '.data_get($notifiable, 'name', 'equipo de Prácticas Profesionales'))
            ->line($this->texto())
            ->line("**Organización:** {$e->razon_social} ({$e->rfc}, {$e->tipo_organismo->label()})")
            ->line("**Contacto:** {$contacto}")
            ->line('**Carreras de interés:** '.$c->dictamenes->map(fn ($d) => $d->carrera->nombre)->join('; '))
            ->line("**Plazas estimadas:** {$c->plazas_estimadas}");

        if (! $e->expedienteCompleto()) {
            $mensaje->line('Su expediente aún está incompleto: recibirás otro aviso cuando suba documentos.');
        }

        return $mensaje
            ->action('Revisar solicitud', url($this->ruta()))
            ->salutation('Vinculación · Prácticas Profesionales CUCSH');
    }

    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'convenio_id' => $this->convenio->id,
            'empresa' => $this->convenio->empresa->razon_social,
        ];
    }
}
