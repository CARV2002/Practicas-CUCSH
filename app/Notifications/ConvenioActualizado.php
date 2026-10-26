<?php

namespace App\Notifications;

use App\Models\Convenio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A la empresa: su convenio avanzó de etapa (elimina la "espera pasiva"). */
class ConvenioActualizado extends Notification
{
    use Queueable;

    public function __construct(public Convenio $convenio, public string $mensaje)
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
            ->subject('Actualización de tu convenio con el CUCSH')
            ->line($this->mensaje)
            ->line('Puedes consultar el estado completo de tu trámite en la plataforma de Prácticas Profesionales.');
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'convenio_id' => $this->convenio->id,
            'estado' => $this->convenio->estado->value,
            'mensaje' => $this->mensaje,
        ];
    }
}
