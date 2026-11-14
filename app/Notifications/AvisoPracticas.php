<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

abstract class AvisoPracticas extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    abstract protected function texto(): string;

    /** Ruta INTERNA (relativa) a la que lleva el aviso. */
    abstract protected function ruta(): string;

    protected function detalle(): ?string
    {
        return null;
    }

    protected function asunto(): string
    {
        return 'Aviso de Prácticas Profesionales CUCSH';
    }

    protected function porCorreo(): bool
    {
        return true;
    }

    /** Llave opcional para no repetir avisos programados. */
    protected function clave(): ?string
    {
        return null;
    }

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return $this->porCorreo() ? ['database', 'mail'] : ['database'];
    }

    /** La campana se escribe al instante; el correo va a la cola (en pruebas, con driver sync, todo es inmediato). */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => config('queue.default')];
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Hola '.$notifiable->name)
            ->line($this->texto())
            ->action('Abrir en la plataforma', url($this->ruta()))
            ->salutation('Coordinación de Prácticas Profesionales');
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return array_filter([
            'tipo' => class_basename(static::class),
            'mensaje' => $this->texto(),
            'detalle' => $this->detalle(),
            'url' => $this->ruta(),
            'clave' => $this->clave(),
        ], fn ($v) => $v !== null);
    }
}