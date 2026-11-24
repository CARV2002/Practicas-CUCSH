<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Dictamen;
use App\Models\Convenio;
use App\Models\Empresa;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Correo automático al buzón institucional de la Unidad Jurídica (no tiene cuenta en el sistema) cuando
 * Vinculación turna un borrador: adjunta la versión turnada (Word editable + PDF) y los documentos
 * constitutivos de la empresa. Jurídica responde por correo u oficio y Lorena registra el dictamen.
 */
class BorradorTurnadoJuridica extends Notification
{
    /** Documentos de la empresa que necesita Jurídica para revisar las declaraciones. */
    public const DOCUMENTOS_EMPRESA = ['acta_constitutiva', 'documento_creacion', 'nombramiento_representante', 'poder_notarial', 'identificacion_representante'];

    /** @param Media[] $borrador */
    public function __construct(public Convenio $convenio, public int $version, public array $borrador, public string $remitente) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $empresa = $this->convenio->empresa;
        $carreras = $this->convenio->dictamenes()->where('dictamen', Dictamen::Aprobatorio->value)->with('carrera')->get()
            ->map(fn ($d) => $d->carrera->nombre)->join(', ', ' y ');

        $mensaje = (new MailMessage)
            ->subject("Borrador de convenio v{$this->version} para dictamen jurídico · {$empresa->razon_social}")
            ->greeting('Unidad Jurídica del CUCSH')
            ->line("Vinculación turna para su Dictamen de Viabilidad Jurídica la versión {$this->version} del convenio específico de prácticas profesionales con {$empresa->razon_social} (RFC {$empresa->rfc}).")
            ->line('Carreras incluidas: '.($carreras ?: 'sin carreras aprobadas').'.')
            ->line('Se adjuntan el borrador en Word (editable, para control de cambios), su versión en PDF y los documentos constitutivos de la organización.')
            ->line('Favor de responder a este correo con el dictamen o las observaciones; Vinculación las registrará en la plataforma.')
            ->salutation("Atentamente,\n{$this->remitente}\nVinculación · Prácticas Profesionales CUCSH");

        foreach ($this->borrador as $archivo) {
            $mensaje->attach($archivo->getPath(), ['as' => $archivo->file_name, 'mime' => $archivo->mime_type]);
        }

        foreach (self::DOCUMENTOS_EMPRESA as $coleccion) {
            if ($doc = $empresa->documentoVigente($coleccion)) {
                $mensaje->attach($doc->getPath(), ['as' => Empresa::nombreDocumento($coleccion).'.'.pathinfo($doc->file_name, PATHINFO_EXTENSION), 'mime' => $doc->mime_type]);
            }
        }

        return $mensaje;
    }
}
