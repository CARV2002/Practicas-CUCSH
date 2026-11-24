<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Convenio;

/** A la empresa: su convenio avanzó de etapa (elimina la "espera pasiva"). */
class ConvenioActualizado extends AvisoPracticas
{
    public function __construct(public Convenio $convenio, public string $mensaje, public bool $correo = false, public ?string $asuntoCorreo = null) {}

    /**
     * La empresa sigue cada etapa en la campana; solo lo que le pide actuar (p. ej. un rechazo del dictamen
     * académico para que ajuste sus actividades) le llega además por correo.
     */
    protected function porCorreo(): bool
    {
        return $this->correo;
    }

    protected function texto(): string
    {
        return $this->mensaje;
    }

    protected function ruta(): string
    {
        return route('empresa.convenio', absolute: false);
    }

    protected function detalle(): ?string
    {
        return $this->convenio->folio ?? 'Solicitud de convenio';
    }

    protected function asunto(): string
    {
        return $this->asuntoCorreo ?? 'Actualización de tu convenio con el CUCSH';
    }

    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'convenio_id' => $this->convenio->id,
            'estado' => $this->convenio->estado->value,
        ];
    }
}
