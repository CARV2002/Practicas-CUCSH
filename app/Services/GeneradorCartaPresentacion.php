<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Personal;
use App\Models\Postulacion;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Carta de Presentación que emite la Coordinación de la carrera (.docx editable),
 * con los datos reales del alumno: nombre, código, carrera, grado y turno.
 */
class GeneradorCartaPresentacion
{
    /** @return string ruta del archivo temporal generado */
    public function generar(Postulacion $postulacion, Personal $firma): string
    {
        $postulacion->loadMissing(['alumno.user', 'alumno.carrera.division', 'programa.convenio.empresa']);
        $alumno = $postulacion->alumno;
        $carrera = $alumno->carrera;
        $programa = $postulacion->programa;
        $empresa = $programa->convenio?->empresa;
        $hoy = Carbon::now()->locale('es');

        $destinatario = $empresa
            ? ($empresa->representante_legal ?: $empresa->contacto_nombre)
            : 'Titular del área receptora';
        $institucion = $empresa->razon_social ?? 'Centro Universitario de Ciencias Sociales y Humanidades (proyecto interno)';

        $doc = new PhpWord;
        $doc->setDefaultFontName('Arial');
        $doc->setDefaultFontSize(11);
        $s = $doc->addSection(['marginTop' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1700, 'marginRight' => 1700]);

        $derecha = ['alignment' => Jc::END, 'spaceAfter' => 240];
        $just = ['alignment' => Jc::BOTH, 'spaceAfter' => 200, 'lineHeight' => 1.3];

        $s->addText('UNIVERSIDAD DE GUADALAJARA', ['bold' => true], ['alignment' => Jc::CENTER]);
        $s->addText('Centro Universitario de Ciencias Sociales y Humanidades', [], ['alignment' => Jc::CENTER]);
        $s->addText(mb_strtoupper("Coordinación de la {$carrera->nombre}"), ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 360]);

        $s->addText('Asunto: Carta de presentación para prácticas profesionales', ['bold' => true], $derecha);
        $s->addText('Zapopan, Jalisco, a '.$hoy->isoFormat('D [de] MMMM [de] YYYY'), [], $derecha);

        $s->addText(mb_strtoupper($destinatario), ['bold' => true]);
        $s->addText(mb_strtoupper($institucion), ['bold' => true]);
        $s->addText('P R E S E N T E', ['bold' => true], ['spaceAfter' => 240]);

        $grado = $alumno->grado ? "{$alumno->grado}.º semestre" : 'semestre no registrado';
        $turno = $alumno->turno?->label() ?? 'no registrado';

        $s->addText(
            "Por medio de la presente me permito presentar a {$alumno->user->name}, con código de alumno {$alumno->num_control}, "
            ."estudiante del {$grado}, turno {$turno}, de la {$carrera->nombre}, adscrita a la {$carrera->division->nombre}, "
            ."quien está interesado(a) en realizar sus prácticas profesionales en el programa «{$programa->titulo}».",
            [],
            $just
        );
        $s->addText(
            "El alumno(a) deberá cubrir un total de {$carrera->horas_requeridas} horas de práctica, conforme al reglamento de su carrera. "
            .'Le solicitamos atentamente que, de ser aceptado(a), nos haga llegar la carta de aceptación correspondiente '
            .'indicando las actividades a desarrollar, el horario y el nombre de su asesor(a) en la institución.',
            [],
            $just
        );
        $s->addText('Sin otro particular, agradezco de antemano la atención brindada.', [], $just);

        $s->addTextBreak(2);
        $s->addText('A T E N T A M E N T E', ['bold' => true], ['alignment' => Jc::CENTER]);
        $s->addText('"Piensa y Trabaja"', ['italic' => true], ['alignment' => Jc::CENTER]);
        $s->addTextBreak(3);
        $s->addText(mb_strtoupper($firma->user->name), ['bold' => true], ['alignment' => Jc::CENTER]);
        $s->addText($firma->puesto ?? "Coordinación de la {$carrera->nombre}", [], ['alignment' => Jc::CENTER]);

        $ruta = tempnam(sys_get_temp_dir(), 'carta').'.docx';
        IOFactory::createWriter($doc, 'Word2007')->save($ruta);

        return $ruta;
    }
}
