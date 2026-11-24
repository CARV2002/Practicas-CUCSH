<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Convenio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Versiones del borrador del convenio (v1, v2, v3…): las que adjunta Vinculación, las que genera el sistema
 * con la plantilla del CUCSH y los juegos finales con la fecha real de firma.
 *
 * Cada versión guarda su Word (para las correcciones de Jurídica con control de cambios) y, si LibreOffice
 * está disponible, un PDF limpio para imprimir los 3 juegos.
 */
class BorradoresConvenio
{
    public function __construct(
        private GeneradorBorradorConvenio $generador,
        private ConvertidorPdf $convertidor,
    ) {}

    /**
     * Borrador redactado fuera del sistema. Si es Word, también se deja su PDF.
     *
     * @return array{version: int, pdf: bool} pdf=false si era Word y no se pudo convertir
     */
    public function adjuntar(Convenio $convenio, UploadedFile $archivo, int $vigenciaAnios): array
    {
        $convenio->update(['vigencia_anios' => $vigenciaAnios]);

        // Es una versión más (p. ej. el Word que Jurídica devolvió con control de cambios)
        $version = $convenio->siguienteVersionBorrador();
        $media = $convenio->addMedia($archivo)
            ->withCustomProperties(['version' => $version, 'propio' => true])
            ->toMediaCollection('borrador_convenio');

        if ($media->mime_type === 'application/pdf') {
            return ['version' => $version, 'pdf' => true];
        }

        $pdf = $this->convertidor->convertir($media->getPath());

        return ['version' => $version, 'pdf' => $this->guardarPdf($convenio, $pdf, "convenio-v{$version}.pdf", ['version' => $version])];
    }

    /**
     * Borrador redactado por el sistema con los datos que revisó Vinculación.
     *
     * @param  array<string, mixed>  $datos  campos de GenerarBorradorRequest
     * @return array{version: int, pdf: bool}
     */
    public function generar(Convenio $convenio, array $datos): array
    {
        $convenio->update(['vigencia_anios' => (int) $datos['vigencia_anios']]);
        $convenio->load('empresa');

        $ruta = $this->generador->generar($convenio, $datos);
        $version = $convenio->siguienteVersionBorrador();

        // El PDF se convierte antes de que Media Library mueva el .docx a su carpeta
        $pdf = $this->convertidor->convertir($ruta);

        $convenio->addMedia($ruta)
            ->usingFileName("borrador-convenio-v{$version}.docx")
            // Se guardan los datos para volver a generarlo con la fecha real al imprimir los juegos finales
            ->withCustomProperties(['version' => $version, 'datos' => Arr::except($datos, ['fecha_firma']), 'fecha_firma' => $datos['fecha_firma'] ?? null])
            ->toMediaCollection('borrador_convenio');

        return ['version' => $version, 'pdf' => $this->guardarPdf($convenio, $pdf, "convenio-v{$version}.pdf", ['version' => $version])];
    }

    /**
     * Juegos finales para firma: el mismo borrador que aprobó Jurídica, ahora con la fecha real impresa.
     *
     * @param  array<string, mixed>  $base  datos del borrador vigente (Convenio::datosBorradorVigente)
     * @return int versión creada
     */
    public function juegosFinales(Convenio $convenio, array $base, string $fechaFirma): int
    {
        $convenio->load('empresa');

        $ruta = $this->generador->generar($convenio, $base + ['fecha_firma' => $fechaFirma, 'vigencia_anios' => $convenio->duracionAnios()]);
        $version = $convenio->siguienteVersionBorrador();
        $propiedades = ['version' => $version, 'datos' => $base, 'fecha_firma' => $fechaFirma, 'final' => true];

        // El PDF se convierte antes de que Media Library mueva el .docx a su carpeta
        $pdf = $this->convertidor->convertir($ruta);

        $convenio->addMedia($ruta)->usingFileName("convenio-final-v{$version}.docx")->withCustomProperties($propiedades)->toMediaCollection('borrador_convenio');
        $this->guardarPdf($convenio, $pdf, "convenio-final-v{$version}.pdf", $propiedades);

        return $version;
    }

    /**
     * Agrega el PDF ya convertido como «borrador_pdf». Devuelve false si LibreOffice no pudo convertirlo.
     *
     * @param  array<string, mixed>  $propiedades
     */
    private function guardarPdf(Convenio $convenio, ?string $pdf, string $nombre, array $propiedades): bool
    {
        if (! $pdf) {
            return false;
        }

        $convenio->addMedia($pdf)->usingFileName($nombre)->withCustomProperties($propiedades)->toMediaCollection('borrador_pdf');
        File::deleteDirectory(dirname($pdf));   // carpeta temporal de LibreOffice

        return true;
    }
}
