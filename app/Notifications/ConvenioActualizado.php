<?php

namespace App\Notifications;

use App\Models\Convenio;

/** A la empresa: su convenio avanzó de etapa (elimina la "espera pasiva"). */
class ConvenioActualizado extends AvisoPracticas
{
    public function __construct(public Convenio $convenio, public string $mensaje)
    {
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
        return 'Actualización de tu convenio con el CUCSH';
    }

    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'convenio_id' => $this->convenio->id,
            'estado' => $this->convenio->estado->value,
        ];
    }
}