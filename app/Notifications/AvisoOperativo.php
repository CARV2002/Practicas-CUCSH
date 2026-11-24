<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Aviso del día a día (campana y, si se pide, correo): mensaje, enlace a donde se resuelve y detalle opcional.
 */
class AvisoOperativo extends AvisoPracticas
{
    public function __construct(
        public string $mensaje,
        public string $url,
        public ?string $detalle = null,
        public bool $correo = false,
        public ?string $asunto = null,
        public ?string $clave = null,
    ) {}

    protected function texto(): string
    {
        return $this->mensaje;
    }

    protected function ruta(): string
    {
        return $this->url;
    }

    protected function detalle(): ?string
    {
        return $this->detalle;
    }

    protected function porCorreo(): bool
    {
        return $this->correo;
    }

    protected function asunto(): string
    {
        return $this->asunto ?? parent::asunto();
    }

    protected function clave(): ?string
    {
        return $this->clave;
    }
}
