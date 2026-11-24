<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Revisión del expediente del alumno: kardex e IMSS se entregan juntos y se revisan en el mismo momento.
 *  - autorizar: valida ambos; el alumno ya puede postularse a una vacante.
 *  - devolver: rechaza el o los documentos con su motivo; el alumno reemplaza solo el que falló.
 *
 * Si algo impide la decisión, lanza ValidationException con el mensaje para la coordinación.
 */
class RevisarExpedienteAlumno
{
    /**
     * Devuelve los documentos marcados. Los ya validados no se tocan.
     *
     * @param  list<string>  $claves  'kardex' y/o 'imss'
     * @return list<string> nombres de lo que se devolvió («el kardex», «la constancia IMSS»)
     */
    public function devolver(Alumno $alumno, array $claves, string $motivo, int $revisorId): array
    {
        $nombres = [];
        foreach ($claves as $clave) {
            [$media, $nombre] = $this->documentos($alumno)[$clave];
            if ($media && $media->getCustomProperty('estado') !== ValidacionEstado::Validada->value) {
                $alumno->resolverDocumento($media, ValidacionEstado::Rechazada, $motivo, $revisorId);
                $nombres[] = $nombre;
            }
        }

        if ($nombres === []) {
            throw ValidationException::withMessages(['documentos' => 'Esos documentos ya están validados: no se pueden devolver.']);
        }

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: 'Tu coordinación te devolvió '.implode(' y ', $nombres)
                .". Motivo: «{$motivo}». Qué hacer: entra a «Mis documentos › Tu expediente», busca la tarjeta marcada en rojo y sube el PDF corregido; en cuanto lo valide podrás postularte.",
            url: route('alumno.mis-documentos', absolute: false),
            correo: true,
            asunto: 'Corrige un documento de tu expediente de prácticas',
        ));

        return $nombres;
    }

    /** Valida kardex e IMSS juntos: deben existir, ninguno estar devuelto, el IMSS vigente y los créditos completos. */
    public function autorizar(Alumno $alumno, int $revisorId): void
    {
        $documentos = $this->documentos($alumno);

        foreach ($documentos as [$media, $nombre]) {
            if ($media === null) {
                $this->impedir('Falta '.$nombre.' del alumno: no se puede autorizar.');
            }
            if ($media->getCustomProperty('estado') === ValidacionEstado::Rechazada->value) {
                $this->impedir("Devolviste {$nombre} al alumno: espera a que suba el archivo corregido.");
            }
        }
        if (! $alumno->tieneImssVigenteHasta(now())) {
            $this->impedir('La vigencia del IMSS que capturó el alumno ya pasó: devuélvelo para que suba una constancia actual.');
        }
        if ((float) $alumno->porcentaje_creditos < (float) $alumno->carrera->porcentaje_creditos_minimo) {
            $this->impedir('Los créditos del alumno están por debajo del mínimo de su carrera: no se puede autorizar.');
        }

        foreach ($documentos as [$media]) {
            if ($media->getCustomProperty('estado') !== ValidacionEstado::Validada->value) {
                $alumno->resolverDocumento($media, ValidacionEstado::Validada, null, $revisorId);
            }
        }

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: 'Tu coordinación validó tu kardex y tu constancia IMSS. ¡Ya puedes postularte a una vacante de tu carrera!',
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Ya puedes postularte a prácticas profesionales',
        ));
    }

    /** @return array<string, array{0: Media|null, 1: string}> archivo vigente y cómo se nombra en los avisos */
    private function documentos(Alumno $alumno): array
    {
        return [
            'kardex' => [$alumno->documentoVigente('kardex'), 'el kardex'],
            'imss' => [$alumno->documentoVigente('constancia_imss'), 'la constancia IMSS'],
        ];
    }

    private function impedir(string $mensaje): never
    {
        throw ValidationException::withMessages(['revision' => $mensaje]);
    }
}
