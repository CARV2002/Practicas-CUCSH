<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Convierte un .docx a PDF con LibreOffice sin interfaz (soffice --headless).
 * Respeta el formato oficial del Word (márgenes, tipografía, tabla de firmas) y no agrega dependencias PHP.
 *
 * Requiere LibreOffice instalado en el servidor. Ruta configurable con PRACTICAS_SOFFICE.
 */
class ConvertidorPdf
{
    /** @return string|null ruta del PDF generado, o null si LibreOffice no está disponible o falló */
    public function convertir(string $docx): ?string
    {
        if (!config('practicas.pdf.habilitado')) {
            return null;
        }

        $binario = $this->binario();
        if ($binario === null) {
            Log::warning('ConvertidorPdf: no se encontró LibreOffice (soffice). Configura PRACTICAS_SOFFICE.');

            return null;
        }

        $salida = storage_path('app/tmp-pdf/' . uniqid('pdf', true));
        File::ensureDirectoryExists($salida);

        // Perfil propio: evita choques con una sesión de LibreOffice abierta en el servidor
        $perfil = 'file:///' . ltrim(str_replace('\\', '/', storage_path('app/lo-perfil')), '/');

        try {
            // El servidor web (Apache, artisan serve) arranca PHP sin variables del sistema;
            // LibreOffice necesita una carpeta temporal y, en Windows, SystemRoot.
            $temporal = storage_path('app/lo-temp');
            File::ensureDirectoryExists($temporal);
            $entorno = array_filter([
                'TEMP' => $temporal,
                'TMP' => $temporal,
                'TMPDIR' => $temporal,
                'HOME' => storage_path('app/lo-perfil'),
                'USERPROFILE' => storage_path('app/lo-perfil'),
                'SystemRoot' => getenv('SystemRoot') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:\\Windows' : null),
                'PATH' => getenv('PATH') ?: null,
            ]);

            $proceso = new Process([
                $binario, '--headless', '--norestore', "-env:UserInstallation={$perfil}",
                '--convert-to', 'pdf', '--outdir', $salida, $docx,
            ], null, $entorno);
            $proceso->setTimeout((int) config('practicas.pdf.timeout', 90));
            $proceso->run();

            $pdf = $salida . DIRECTORY_SEPARATOR . pathinfo($docx, PATHINFO_FILENAME) . '.pdf';

            if (!$proceso->isSuccessful() || !is_file($pdf)) {
                Log::warning('ConvertidorPdf: la conversión falló.', ['codigo' => $proceso->getExitCode(), 'salida' => $proceso->getErrorOutput() ?: $proceso->getOutput()]);

                return null;
            }

            return $pdf;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function binario(): ?string
    {
        $configurado = config('practicas.pdf.soffice');
        $candidatos = array_filter([
            $configurado,
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ]);

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
