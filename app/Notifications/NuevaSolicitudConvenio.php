<?php

namespace App\Notifications;

use App\Models\Convenio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A la Coordinación de Prácticas: llegó una solicitud de convenio nueva. */
class NuevaSolicitudConvenio extends Notification
{
    use Queueable;

    public function __construct(public Convenio $convenio)
    {
    }

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva solicitud de convenio')
            ->line("La empresa {$this->convenio->empresa->razon_social} envió una solicitud de convenio.")
            ->line('Revisa su documentación en la plataforma.');
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'convenio_id' => $this->convenio->id,
            'empresa' => $this->convenio->empresa->razon_social,
            'mensaje' => 'Nueva solicitud de convenio.',
        ];
    }
}
