<?php

use App\Actions\ActivarPractica;
use App\Actions\EmitirCartaPresentacion;
use App\Actions\RegistrarCartaAceptacion;
use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoOrganismo;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CartaTermino;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\ConvenioActualizado;
use App\Notifications\NuevaSolicitudConvenio;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| El proceso real de Coordinación de Prácticas, de punta a punta
|--------------------------------------------------------------------------
| Lorena (rol administrador) ejecuta el proceso y captura lo que resuelven divisiones,
| Jurídica y Rectoría. La empresa recibe avisos. Todo corre sobre la BD de pruebas.
*/

function pdfFalso(string $nombre = 'documento.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function solicitudDeConvenio(): array
{
    return [
        'objetivos' => 'Recibir practicantes de ciencias sociales.',
        'actividades_propuestas' => 'Apoyo en archivo y atención a usuarios.',
        'plazas_estimadas' => 4,
    ];
}

function validarExpediente(Empresa $empresa, int $revisorUserId): void
{
    foreach (Empresa::coleccionesObligatorias() as $coleccion) {
        $media = $empresa->addMedia(pdfFalso())->toMediaCollection($coleccion);
        $empresa->resolverDocumento($media, ValidacionEstado::Validada, null, $revisorUserId);
    }
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesSeeder::class);

    $division = Division::create(['nombre' => 'División de prueba']);
    $datosCarrera = ['division_id' => $division->id, 'horas_requeridas' => 250,
        'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abogado = Carrera::create($datosCarrera + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->historia = Carrera::create($datosCarrera + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    $this->userLorena = User::factory()->create();
    $this->userLorena->assignRole(Rol::Administrador->value);
    $this->lorena = Personal::create(['user_id' => $this->userLorena->id, 'puesto' => 'Coordinación de Prácticas']);

    $this->userEmpresa = User::factory()->create();
    $this->userEmpresa->assignRole(Rol::Empresa->value);
    $this->empresa = Empresa::create([
        'user_id' => $this->userEmpresa->id, 'rfc' => 'EPR010101AB1', 'razon_social' => 'Empresa de Prueba',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ]);

    $this->workflow = app(ConvenioWorkflow::class);

    /** Lleva un convenio hasta "formalizado" con Abogado aprobada e Historia negativa. */
    $this->convenioFormalizado = function (): Convenio {
        $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id, $this->historia->id]);
        validarExpediente($this->empresa, $this->userLorena->id);
        $c = $this->workflow->enviarAValidacionAcademica($c, $this->lorena);

        $this->workflow->registrarDictamenCarrera($c->dictamenes()->where('carrera_id', $this->abogado->id)->first(), Dictamen::Aprobatorio, null, $this->lorena);
        $this->workflow->registrarDictamenCarrera($c->dictamenes()->where('carrera_id', $this->historia->id)->first(), Dictamen::Negativo, 'Sin actividades afines.', $this->lorena);

        $c->addMedia(pdfFalso('borrador.pdf'))->toMediaCollection('borrador_convenio');
        $this->workflow->turnarAJuridica($c, $this->lorena);
        $this->workflow->registrarDictamenJuridico($c, SeguimientoEstado::Aprobado, null, $this->lorena);
        $this->workflow->registrarFirma($c, EtapaConvenio::FirmaEmpresa, $this->lorena);
        $this->workflow->registrarFirma($c, EtapaConvenio::FirmaRectoria, $this->lorena);

        $c->addMedia(pdfFalso('firmado.pdf'))->toMediaCollection('convenio_firmado');

        return $this->workflow->formalizar($c, $this->lorena, 'CV-2026-001', today()->subDay(), today()->addYears(3));
    };
});

/* ------------------------------ CONVENIO ------------------------------ */

test('un convenio recorre todo el proceso y se formaliza', function () {
    $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id, $this->historia->id]);

    expect($c->estado)->toBe(ConvenioEstado::Solicitado)->and($c->dictamenes)->toHaveCount(2);
    Notification::assertSentTo($this->userLorena, NuevaSolicitudConvenio::class);

    // El convenio de arriba queda en trámite; aquí se lleva OTRO convenio por todas las etapas.
    $c = ($this->convenioFormalizado)->call($this);
    expect($c->estado)->toBe(ConvenioEstado::Formalizado)
        ->and($c->folio)->toBe('CV-2026-001')
        ->and($c->estaVigente())->toBeTrue()
        ->and($c->autorizaCarrera($this->abogado->id))->toBeTrue()
        ->and($c->autorizaCarrera($this->historia->id))->toBeFalse();

    expect(Convenio::vigentes()->count())->toBe(1);
    Notification::assertSentTo($this->userEmpresa, ConvenioActualizado::class);

    // La bitácora conserva cada etapa abierta y cerrada
    $etapas = $c->seguimientos->pluck('etapa')->map->value->all();
    expect($etapas)->toContain('revision_documentos', 'turno_academico', 'redaccion_borrador', 'dictamen_juridico', 'firma_empresa', 'firma_rectoria', 'archivo_distribucion')
        ->and($c->etapasAbiertas()->count())->toBe(0);
});

test('no se turna a las carreras con el expediente incompleto', function () {
    $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id]);

    expect(fn () => $this->workflow->enviarAValidacionAcademica($c, $this->lorena))
        ->toThrow(ValidationException::class, 'expediente');
});

test('si todas las carreras dictaminan en negativo el convenio se rechaza y se avisa a la empresa', function () {
    $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id, $this->historia->id]);
    validarExpediente($this->empresa, $this->userLorena->id);
    $c = $this->workflow->enviarAValidacionAcademica($c, $this->lorena);

    foreach ($c->dictamenes as $d) {
        $this->workflow->registrarDictamenCarrera($d, Dictamen::Negativo, 'No aplica.', $this->lorena);
    }

    expect($c->refresh()->estado)->toBe(ConvenioEstado::Rechazado)
        ->and($c->etapasAbiertas()->count())->toBe(0);
    Notification::assertSentTo($this->userEmpresa, ConvenioActualizado::class, fn ($n) => str_contains($n->mensaje, 'rechazada'));
});

test('un dictamen negativo exige observaciones', function () {
    $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id]);
    validarExpediente($this->empresa, $this->userLorena->id);
    $c = $this->workflow->enviarAValidacionAcademica($c, $this->lorena);

    expect(fn () => $this->workflow->registrarDictamenCarrera($c->dictamenes->first(), Dictamen::Negativo, '  ', $this->lorena))
        ->toThrow(ValidationException::class);
});

test('cuando Jurídica observa el borrador el historial conserva ambas revisiones', function () {
    $c = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id]);
    validarExpediente($this->empresa, $this->userLorena->id);
    $c = $this->workflow->enviarAValidacionAcademica($c, $this->lorena);
    $this->workflow->registrarDictamenCarrera($c->dictamenes->first(), Dictamen::Aprobatorio, null, $this->lorena);

    // No se puede turnar a Jurídica sin borrador adjunto
    expect(fn () => $this->workflow->turnarAJuridica($c, $this->lorena))->toThrow(ValidationException::class, 'borrador');

    $c->addMedia(pdfFalso('borrador-v1.pdf'))->toMediaCollection('borrador_convenio');
    $this->workflow->turnarAJuridica($c, $this->lorena);
    $this->workflow->registrarDictamenJuridico($c, SeguimientoEstado::Observado, 'Corregir cláusula tercera.', $this->lorena);

    expect($c->refresh()->estado)->toBe(ConvenioEstado::EnRevisionJuridica);

    $c->addMedia(pdfFalso('borrador-v2.pdf'))->toMediaCollection('borrador_convenio');
    $this->workflow->turnarAJuridica($c, $this->lorena);
    $this->workflow->registrarDictamenJuridico($c, SeguimientoEstado::Aprobado, null, $this->lorena);

    $juridicos = $c->seguimientos()->where('etapa', 'dictamen_juridico')->pluck('estado')->map->value->all();
    expect($juridicos)->toBe(['observado', 'aprobado'])
        ->and($c->refresh()->estado)->toBe(ConvenioEstado::EnFirmas)
        ->and($c->getMedia('borrador_convenio'))->toHaveCount(2);   // las dos versiones quedan guardadas
});

test('las firmas van en orden y no se formaliza sin el escaneo firmado', function () {
    $c = ($this->convenioFormalizado)->call($this);
    expect($c->estado)->toBe(ConvenioEstado::Formalizado);

    $otra = $this->workflow->solicitar($this->empresa, solicitudDeConvenio(), [$this->abogado->id]);
    expect(fn () => $this->workflow->registrarFirma($otra, EtapaConvenio::FirmaRectoria, $this->lorena))
        ->toThrow(ValidationException::class);
});

test('la oferta exige carrera aprobada en el convenio y convenio vigente', function () {
    $c = ($this->convenioFormalizado)->call($this);
    $datos = ['convenio_id' => $c->id, 'modalidad' => 'externa', 'ciclo' => '2027A', 'titulo' => 'Auxiliar',
        'descripcion' => 'Apoyo', 'actividades' => ['Catalogar'], 'perfil_requerido' => 'Ordenado', 'cupos_totales' => 2];

    // Historia tuvo dictamen negativo: no puede ofertar bajo este convenio
    expect(fn () => Programa::create($datos + ['carrera_id' => $this->historia->id]))
        ->toThrow(ValidationException::class, 'dictamen aprobatorio');

    $programa = Programa::create($datos + ['carrera_id' => $this->abogado->id]);
    $programa->update(['estado' => ProgramaEstado::Publicado]);
    expect($programa->refresh()->estado)->toBe(ProgramaEstado::Publicado);

    // Modalidad interna: sin convenio
    $interno = Programa::create(['convenio_id' => null, 'carrera_id' => $this->abogado->id, 'modalidad' => 'interna',
        'ciclo' => '2027A', 'titulo' => 'Proyecto interno', 'descripcion' => 'x', 'actividades' => ['y'],
        'perfil_requerido' => 'z', 'cupos_totales' => 1]);
    expect($interno->esInterno())->toBeTrue();
});

/* ------------------------------ PRÁCTICA ------------------------------ */

function alumnoDe(Carrera $carrera, string $codigo, ?string $imss = null): Alumno
{
    $user = User::factory()->create();
    $user->assignRole(Rol::Alumno->value);

    return Alumno::create([
        'user_id' => $user->id, 'carrera_id' => $carrera->id, 'num_control' => $codigo,
        'vigencia_imss' => $imss ?? now()->addYear()->toDateString(),
    ]);
}

function postular(Alumno $alumno, Programa $programa, float $creditos = 60): Postulacion
{
    return Postulacion::create([
        'alumno_id' => $alumno->id, 'programa_id' => $programa->id,
        'porcentaje_creditos_declarado' => $creditos, 'fecha_postulacion' => today(),
    ]);
}

test('de la postulación a la acreditación: reglas, cupos, horas y cierre', function () {
    $c = ($this->convenioFormalizado)->call($this);
    $programa = Programa::create(['convenio_id' => $c->id, 'carrera_id' => $this->abogado->id, 'modalidad' => 'externa',
        'ciclo' => '2027A', 'titulo' => 'Auxiliar', 'descripcion' => 'Apoyo', 'actividades' => ['Catalogar'],
        'perfil_requerido' => 'Ordenado', 'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);

    $ana = alumnoDe($this->abogado, '215000001');
    $luis = alumnoDe($this->abogado, '215000002');
    $emitir = app(EmitirCartaPresentacion::class);

    // No cumple el % de créditos
    expect(fn () => $emitir(postular($ana, $programa, 40), $this->lorena))->toThrow(ValidationException::class, 'créditos');
    Postulacion::query()->delete();

    $pAna = postular($ana, $programa, 62.5);
    $emitir($pAna, $this->lorena);
    expect($pAna->refresh()->estado)->toBe(PostulacionEstado::CartaPresentacionEmitida)
        ->and($programa->refresh()->cupos_disponibles)->toBe(0);

    // El único cupo ya está reservado
    expect(fn () => $emitir(postular($luis, $programa), $this->lorena))->toThrow(ValidationException::class, 'cupos');

    // La carta de aceptación es obligatoria
    $registrar = app(RegistrarCartaAceptacion::class);
    expect(fn () => $registrar($pAna))->toThrow(ValidationException::class, 'carta de aceptación');
    $pAna->addMedia(pdfFalso('aceptacion.pdf'))->toMediaCollection('carta_aceptacion');
    $registrar($pAna);

    // Nace la práctica (sin supervisor: se asigna después); congela las horas de la carrera
    $practica = app(ActivarPractica::class)($pAna->refresh(), $this->lorena, today(), today()->addMonths(6));
    expect($practica->horas_requeridas)->toBe(250)
        ->and($practica->profesor_supervisor_id)->toBeNull()
        ->and($practica->estado)->toBe(PracticaEstado::EnCurso)
        ->and($pAna->refresh()->estado)->toBe(PostulacionEstado::Aceptada);

    // La carta de término no se valida si faltan horas
    $carta = CartaTermino::create(['practica_id' => $practica->id]);
    expect(fn () => $carta->update(['estado_validacion' => ValidacionEstado::Validada]))
        ->toThrow(ValidationException::class, 'insuficientes');

    Reporte::create(['practica_id' => $practica->id, 'tipo' => 'final', 'periodo_inicio' => today(),
        'periodo_fin' => today()->addMonths(6), 'horas_reportadas' => 250, 'estado' => ReporteEstado::Validado]);

    expect($practica->refresh()->progresoTexto())->toBe('250/250');

    $carta->update(['estado_validacion' => ValidacionEstado::Validada]);
    expect($practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);

    // "Acreditada" solo cuando Control Escolar captura en SIIAU
    $oficio = OficioAcreditacion::create(['practica_id' => $practica->id, 'emitido_por' => $this->lorena->id,
        'estado_validacion' => ValidacionEstado::Validada]);
    $oficio->update(['enviado_control_escolar_at' => now()]);
    expect($practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);

    $oficio->update(['capturado_siiau_at' => now()]);
    expect($practica->refresh()->estado)->toBe(PracticaEstado::Acreditada);
});

test('un alumno solo puede tener una empresa a la vez y su otra postulación se cancela', function () {
    $c = ($this->convenioFormalizado)->call($this);
    $nuevo = fn (string $t) => Programa::create(['convenio_id' => $c->id, 'carrera_id' => $this->abogado->id,
        'modalidad' => 'externa', 'ciclo' => '2027A', 'titulo' => $t, 'descripcion' => 'x', 'actividades' => ['y'],
        'perfil_requerido' => 'z', 'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado]);
    $a = $nuevo('Programa A');
    $b = $nuevo('Programa B');

    $ana = alumnoDe($this->abogado, '215000010');
    $pa = postular($ana, $a);
    $pb = postular($ana, $b);

    app(EmitirCartaPresentacion::class)($pa, $this->lorena);

    // Con una carta emitida, no puede abrir otro trámite
    expect(fn () => app(EmitirCartaPresentacion::class)($pb, $this->lorena))
        ->toThrow(ValidationException::class, 'ya tiene una empresa');

    $pa->addMedia(pdfFalso())->toMediaCollection('carta_aceptacion');
    app(RegistrarCartaAceptacion::class)($pa);
    app(ActivarPractica::class)($pa->refresh(), $this->lorena, today(), today()->addMonths(6));

    expect($pb->refresh()->estado)->toBe(PostulacionEstado::Cancelada);
});

test('el IMSS debe cubrir todo el periodo de la práctica', function () {
    $c = ($this->convenioFormalizado)->call($this);
    $programa = Programa::create(['convenio_id' => $c->id, 'carrera_id' => $this->abogado->id, 'modalidad' => 'externa',
        'ciclo' => '2027A', 'titulo' => 'Auxiliar', 'descripcion' => 'x', 'actividades' => ['y'],
        'perfil_requerido' => 'z', 'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);

    $ana = alumnoDe($this->abogado, '215000020', now()->addMonths(2)->toDateString());   // vence antes de terminar
    $p = postular($ana, $programa);
    app(EmitirCartaPresentacion::class)($p, $this->lorena);
    $p->addMedia(pdfFalso())->toMediaCollection('carta_aceptacion');
    app(RegistrarCartaAceptacion::class)($p);

    expect(fn () => app(ActivarPractica::class)($p->refresh(), $this->lorena, today(), today()->addMonths(6)))
        ->toThrow(ValidationException::class, 'IMSS');
});
