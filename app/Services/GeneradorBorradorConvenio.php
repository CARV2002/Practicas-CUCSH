<?php

namespace App\Services;

use App\Enums\Dictamen;
use App\Enums\TipoOrganismo;
use App\Models\Convenio;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/** Arma el "Formato de PP" (convenio específico en materia de prácticas profesionales) como .docx. */
class GeneradorBorradorConvenio
{
    private PhpWord $doc;

    private $s;   // sección

    /**
     * @param  array{representante_nombre:string,representante_cargo:string,constitucion:string,atribuciones:string,facultad_firmante:string,domicilio_legal:string,lugar_firma:string,fecha_firma:string,apoyo_economico?:?string}  $d
     * @return string ruta del archivo temporal generado
     */
    public function generar(Convenio $convenio, array $d): string
    {
        $empresa = $convenio->empresa;
        $entidad = mb_strtoupper($empresa->razon_social);
        $rectora = config('practicas.rectora');
        $fecha = Carbon::parse($d['fecha_firma'])->locale('es');

        $sector = match ($empresa->tipo_organismo) {
            TipoOrganismo::Publico => 'público',
            TipoOrganismo::Privado => 'privado',
            default => 'social',
        };

        $carreras = $convenio->dictamenes()->with('carrera')->where('dictamen', Dictamen::Aprobatorio->value)->get()
            ->map(fn ($x) => $x->carrera->nombre)->join('; ');

        $this->doc = new PhpWord();
        $this->doc->setDefaultFontName('Arial');
        $this->doc->setDefaultFontSize(11);
        $this->s = $this->doc->addSection(['marginTop' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1700, 'marginRight' => 1700]);

        // --- Preámbulo
        $this->p("CONVENIO ESPECÍFICO EN MATERIA DE PRÁCTICAS PROFESIONALES QUE CELEBRAN POR UNA PARTE EL CENTRO UNIVERSITARIO DE CIENCIAS SOCIALES Y HUMANIDADES DE LA UNIVERSIDAD DE GUADALAJARA, EN LO SUCESIVO EL CUCSH REPRESENTADO POR LA RECTORA {$rectora}, Y POR LA OTRA PARTE {$entidad}, EN LO SUCESIVO LA “ENTIDAD”, A TRAVÉS DE SU {$d['representante_cargo']}, {$d['representante_nombre']}, AL TENOR DE LOS ANTECEDENTES, DECLARACIONES Y CLÁUSULAS:", true);

        // --- Antecedentes
        $this->titulo('ANTECEDENTES');
        $this->p('Que dentro de los objetivos del Programa de Apoyo al Sector Productivo y de Servicio (PRODUCE) y del Programa de Apoyo al Desarrollo Social (COMPARTE), que forman parte del Programa General de Trabajo del Acuerdo Universitario para el Desarrollo Sostenible de Jalisco (ACUDE), se prevé fortalecer la vinculación de la Universidad de Guadalajara con el sector productivo y social; y');
        $this->p('El Plan de Desarrollo Institucional Visión 2030 de la Universidad de Guadalajara, señala como objetivo 3.1: Consolidar los programas de vinculación con los sectores público, social y privado; y como estrategia 3.1.3: Fortalecer el desarrollo de prácticas profesionales y servicio social con los diversos sectores.');

        // --- Declaraciones
        $this->titulo('DECLARACIONES');
        $this->p('DECLARA EL CUCSH', true);
        $this->lista(['I', 'II', 'III'], [
            'Que es un órgano desconcentrado de la Universidad de Guadalajara, encargado de cumplir en su área de conocimiento y del ejercicio profesional, los fines que en el orden de la cultura y la educación superior corresponden a esta Casa de Estudios conforme a lo establecido en el artículo 5o. de su Ley Orgánica;',
            'Que el Rector General y Secretario General de esta Casa de Estudios emitieron el 13 de septiembre del 2000 el acuerdo número 05/2000 por el que autoriza a los Rectores de los Centros Universitarios a suscribir convenios de prácticas profesionales; y',
            'Que para efectos del presente convenio señala como su domicilio el inmueble ubicado en '.config('practicas.domicilio_cucsh').'.',
        ]);

        $this->p('DECLARA LA “ENTIDAD”:', true);
        $this->lista(['I', 'II', 'III', 'IV', 'V'], [
            "Que {$empresa->razon_social} acredita su existencia legal mediante {$d['constitucion']}.",
            "Que dentro de sus atribuciones u objeto, relacionados con el presente convenio, se encuentran: {$d['atribuciones']}.",
            "Que {$d['representante_nombre']}, en su carácter de {$d['representante_cargo']}, cuenta con facultades para suscribir y ejecutar el presente convenio: {$d['facultad_firmante']}.",
            "Que señala como su domicilio legal el ubicado en {$d['domicilio_legal']}.",
            "Que su Registro Federal de Contribuyentes es {$empresa->rfc}.",
        ]);

        // --- Cláusulas
        $this->titulo('CLÁUSULAS');
        $this->clausula('PRIMERA', 'El objeto del presente convenio es establecer las bases para la coordinación entre el CUCSH y la “ENTIDAD” en materia de prácticas profesionales.');
        $this->clausula('SEGUNDA', "Las partes están de acuerdo que con las prácticas profesionales a desarrollar por los alumnos, se pretende complementar su formación profesional a través de la aplicación en el sector {$sector} de los conocimientos adquiridos en el programa académico que cursan.");
        $this->clausula('TERCERA', 'Las prácticas profesionales se sujetarán a lo establecido por la normatividad universitaria vigente, a las políticas y lineamientos del Plan Institucional de Desarrollo, al Acuerdo Universitario para el Desarrollo sostenible del Estado de Jalisco (ACUDE), así como a lo previsto en el presente convenio.');

        $this->clausula('CUARTA', 'Las partes acuerdan que para el adecuado desarrollo de las actividades a que se refiere el presente convenio, se formará en un plazo no mayor de 15 días a partir de la fecha de la firma del presente convenio una Comisión Técnica integrada por un representante de cada una, cuyas atribuciones serán las siguientes:');
        $this->lista(['I', 'II', 'III', 'IV', 'V', 'VI'], [
            'Supervisar y evaluar la ejecución del presente convenio;',
            'Determinar dentro de los 15 días siguientes al inicio de cada ciclo escolar el calendario, lugares, horarios y actividades a desarrollar y el número de alumnos requeridos;',
            'Rendir un informe a los titulares de cada una de las partes al término de cada calendario de actividades;',
            'Proponer la modificación, suspensión o cancelación del presente convenio;',
            'Hacer propuestas para el mejor desarrollo de las prácticas profesionales; y',
            'Aquellas que se acuerden para la ejecución del presente convenio.',
        ]);
        $this->p("El representante del CUCSH será nombrado por la {$rectora}, Rectora del CUCSH y el de la “ENTIDAD” será nombrado por el {$d['representante_cargo']}, {$d['representante_nombre']}.");

        $this->clausula('QUINTA', 'La “ENTIDAD” para la ejecución del presente se compromete a:');
        $apoyo = filled($d['apoyo_economico'] ?? null)
            ? 'Otorgar a los alumnos del CUCSH que realizan sus prácticas profesionales la siguiente ayuda o estímulo económico: '.$d['apoyo_economico'].';'
            : 'Otorgar en la medida de sus posibilidades, ayudas o estímulos económicos a los alumnos del CUCSH que realizan sus prácticas profesionales conforme a lo previsto en el presente instrumento;';
        $this->lista(['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'], [
            'Desarrollar dentro de sus instalaciones, pláticas de inducción para los alumnos que vayan a realizar sus prácticas profesionales, informándoles aquellos aspectos que se consideren necesarios para el correcto desarrollo de las mismas;',
            'Permitir el acceso a sus instalaciones a los estudiantes que de conformidad con el presente instrumento vayan a realizar sus prácticas profesionales;',
            'Facilitar la supervisión y evaluación de las prácticas, así como proporcionar la información que sobre las mismas le requiera el CUCSH;',
            'Cuidar que las prácticas profesionales que realizan los alumnos del CUCSH sean acordes a su perfil profesional;',
            'Proporcionar gratuitamente alimentos a los estudiantes del CUCSH que realicen sus prácticas profesionales, cuando por el horario así se requiera;',
            'Proporcionar o cubrir los gastos de transporte de los estudiantes del CUCSH cuando el lugar en donde realizarán sus prácticas profesionales, se encuentre fuera del municipio en el que se encuentre localizadas las instalaciones universitarias a las que asisten regularmente a clases;',
            $apoyo,
            'Desarrollar el siguiente plan de trabajo dentro de la “ENTIDAD”, acorde al perfil profesional de los estudiantes de '.($carreras ?: 'las carreras autorizadas').': '.$convenio->actividades_propuestas.';',
            'Proporcionar un área específica para que los alumnos del CUCSH resguarden con seguridad los objetos de su propiedad, lo anterior cuando sus prácticas profesionales las realicen dentro de sus instalaciones;',
            'Vigilar que las condiciones bajo las que se realicen las prácticas profesionales de los estudiantes del CUCSH sean adecuadas y con las protecciones necesarias de acuerdo a la actividad que se realice, proporcionando el material y equipo que garantice su seguridad;',
            'Notificar al responsable del CUCSH designado conforme a la cláusula cuarta, las irregularidades que se presenten con los estudiantes durante el desarrollo de las prácticas profesionales; y',
            'Garantizar un trato digno a los estudiantes del CUCSH que realicen sus prácticas profesionales.',
        ]);

        $this->clausula('SEXTA', 'El CUCSH se compromete a:');
        $this->lista(['I', 'II', 'III', 'IV'], [
            'Supervisar y evaluar el desarrollo de las prácticas profesionales;',
            'Seleccionar conforme a las actividades a desarrollar y el perfil profesional requerido, los alumnos que realizarán las prácticas profesionales;',
            'Supervisar que los alumnos que vayan a realizar sus prácticas profesionales en la “ENTIDAD” se encuentren inscritos en el Instituto Mexicano del Seguro Social, de conformidad con el decreto publicado en el Diario Oficial de la Federación de fecha 14 de septiembre de 1998, relativo a la incorporación al régimen obligatorio; y',
            'Expedir a los estudiantes una carta de presentación, en la cual se especificará la carrera, grado escolar y turno de clases.',
        ]);

        $this->clausula('SÉPTIMA', 'El CUCSH se compromete además, hacer del conocimiento de los estudiantes, que durante la realización de sus prácticas profesionales se deberán sujetar a lo siguiente:');
        $this->lista(['I', 'II', 'III', 'IV', 'V'], [
            'Estar inscritos como derechohabientes en el Instituto Mexicano del Seguro Social (IMSS), debiendo para tal efecto presentar la constancia correspondiente;',
            'Observar, en lo conducente, las normas de la “ENTIDAD”.',
            'Observar disciplina y buen desempeño de sus prácticas;',
            'Responsabilizarse por el buen uso del material y equipo que utilice en sus prácticas; y',
            'Cuidar la imagen de la Universidad de Guadalajara conduciéndose con respeto, honestidad, honradez y profesionalismo durante la realización de sus prácticas.',
        ]);

        $this->clausula('OCTAVA', 'La “ENTIDAD” y el CUCSH acuerdan que en caso de que la Universidad de Guadalajara modifique la normatividad con relación al desarrollo de las prácticas profesionales, lo anterior se le notificará a la “ENTIDAD” en forma inmediata y se realizarán las modificaciones que en su caso requiera el presente instrumento.');
        $this->clausula('NOVENA', 'Ambas partes acuerdan que la realización de las prácticas profesionales de los alumnos del CUCSH, no genera relación de tipo laboral alguno con la “ENTIDAD”.');
        $this->clausula('DÉCIMA', 'Los trabajadores de las partes que sean designados para la realización de actividades en ejecución de este convenio continuarán en forma absoluta bajo la dirección y dependencia de aquella con la cual tiene establecida su relación laboral.');
        $this->clausula('UNDÉCIMA', 'El presente convenio entrará en vigor a partir de su fecha de firma y tendrá una vigencia por tres años, pudiendo modificarse adicionarse o darse por terminado anticipadamente por mutuo acuerdo entre las partes. Igualmente podrá darse por terminada anticipadamente por cualquiera de las partes previo aviso por escrito que haga la otra, con treinta días de anticipación, sin perjuicio del cumplimiento de las actividades que se encuentren en desarrollo.');
        $this->clausula('DUODÉCIMA', 'Las partes manifiestan que la firma de este convenio y los compromisos contraídos con el, son producto de su buena fe, por lo que realizarán todas las acciones necesarias para su debido cumplimiento; en caso de presentarse alguna discrepancia sobre su interpretación esta será resuelta por mutuo acuerdo.');

        $this->p("Leído el presente instrumento, enteradas las partes del contenido y alcance de cada una de sus cláusulas e indicando que en su celebración no existe dolo, mala fe, o cualquier otro motivo que vicie su consentimiento, lo firma por triplicado en la ciudad de {$d['lugar_firma']}, Jalisco, el {$fecha->translatedFormat('j \d\e F \d\e Y')}.");

        // --- Firmas
        $this->s->addTextBreak(2);
        $tabla = $this->s->addTable();
        $tabla->addRow();
        $c1 = $tabla->addCell(4500);
        $c2 = $tabla->addCell(4500);
        $centro = ['alignment' => Jc::CENTER];
        $c1->addText('POR EL CUCSH', ['bold' => true], $centro);
        $c2->addText('POR LA “ENTIDAD”', ['bold' => true], $centro);
        $c1->addTextBreak(3);
        $c2->addTextBreak(3);
        $c1->addText('_______________________________', null, $centro);
        $c2->addText('_______________________________', null, $centro);
        $c1->addText($rectora, ['bold' => true], $centro);
        $c1->addText(config('practicas.rectora_cargo'), null, $centro);
        $c2->addText(mb_strtoupper($d['representante_nombre']), ['bold' => true], $centro);
        $c2->addText(mb_strtoupper($d['representante_cargo']).' DE '.$entidad, null, $centro);

        $ruta = tempnam(sys_get_temp_dir(), 'convenio').'.docx';
        IOFactory::createWriter($this->doc, 'Word2007')->save($ruta);

        return $ruta;
    }

    private function p(string $texto, bool $negritas = false): void
    {
        $this->s->addText($texto, ['bold' => $negritas], ['alignment' => Jc::BOTH, 'spaceAfter' => 160]);
    }

    private function titulo(string $texto): void
    {
        $this->s->addText($texto, ['bold' => true], ['alignment' => Jc::CENTER, 'spaceBefore' => 200, 'spaceAfter' => 160]);
    }

    private function clausula(string $ordinal, string $texto): void
    {
        $run = $this->s->addTextRun(['alignment' => Jc::BOTH, 'spaceAfter' => 160]);
        $run->addText("{$ordinal}. ", ['bold' => true]);
        $run->addText($texto);
    }

    /** @param string[] $romanos @param string[] $textos */
    private function lista(array $romanos, array $textos): void
    {
        foreach ($textos as $i => $texto) {
            $this->s->addText("{$romanos[$i]}.  {$texto}", null, ['alignment' => Jc::BOTH, 'indentation' => ['left' => 400], 'spaceAfter' => 120]);
        }
    }
}