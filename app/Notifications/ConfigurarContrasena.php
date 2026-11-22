<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace único y temporal (72 h) para que el personal ponga su propia contraseña.
 * El administrador nunca conoce la clave: la cuenta nace con una contraseña aleatoria e inaccesible.
 *   alta        → cuenta nueva de coordinación, Vinculación o administración
 *   restablecer → el administrador pidió reenviar el enlace (olvido o cuenta sin activar)
 */
class ConfigurarContrasena extends Notification
{
    public function __construct(public string $token, public string $motivo = 'alta')
    {
    }

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('cuenta.activar', ['token' => $this->token, 'email' => $notifiable->email]);
        $rol = $notifiable->rolPrincipal()?->label();

        $mensaje = (new MailMessage)
            ->subject($this->motivo === 'alta' ? 'Activa tu cuenta · Prácticas Profesionales CUCSH' : 'Configura tu contraseña · Prácticas Profesionales CUCSH')
            ->greeting('Hola ' . $notifiable->name);

        $mensaje = $this->motivo === 'alta'
            ? $mensaje->line('La administración de Prácticas Profesionales del CUCSH creó tu cuenta' . ($rol ? " de {$rol}" : '') . '.')
                ->line('Para entrar, configura tu propia contraseña con el botón de abajo. Nadie más la conoce.')
            : $mensaje->line('La administración de Prácticas Profesionales del CUCSH te envió un enlace para configurar una nueva contraseña.');

        return $mensaje
            ->action('Configurar mi contraseña', $url)
            ->line('El enlace es personal y vence en 72 horas. Al usarlo, tu correo queda verificado.')
            ->line('Si no esperabas este correo, ignóralo: tu cuenta no se activará.')
            ->salutation('Prácticas Profesionales · CUCSH');
    }
}
