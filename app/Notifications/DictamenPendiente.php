<?php

namespace App\Notifications;

use App\Models\Convenio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sustituye al "Oficio de Turno": avisa a coordinadores y directores que deben dictaminar. */
class DictamenPendiente extends Notification
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
            ->subject('Dictamen de pertinencia académica pendiente')
            ->line("La empresa {$this->convenio->empresa->razon_social} solicita alumnos de su carrera.")
            ->line('Revisa las actividades propuestas y emite tu dictamen en la plataforma.');
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'convenio_id' => $this->convenio->id,
            'empresa' => $this->convenio->empresa->razon_social,
            'mensaje' => 'Dictamen de pertinencia académica pendiente.',
        ];
    }
}
