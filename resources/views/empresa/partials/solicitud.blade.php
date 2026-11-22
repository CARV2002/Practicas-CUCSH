{{--
    Solicitud de convenio por secciones (procedimiento de la empresa, etapa 2; mismas preguntas que el formulario
    oficial «Propuestas de convenio para Prácticas Profesionales, CUCSH»).
    Cada sección se guarda por separado («Guardar avance»): la empresa no pierde lo capturado si le falta un dato.
    * = obligatorio para ENVIAR la solicitud (el avance se puede guardar a medias).
    Variables: $empresa, $faltantes, $borrador, $seccion, $carreras, $propuestaAnterior, $puedeRenovar, $motivoSolicitud.
--}}
@php
    use App\Support\SolicitudEmpresa as S;
    use App\Support\Formatos as F;

    $escritura = \App\Support\DatosNotariales::usaEscritura($empresa->tipo_organismo);
    $completas = collect($faltantes)->filter(fn ($f) => empty($f))->keys();
    $primeraPendiente = collect($faltantes)->filter()->keys()->first() ?? 'perfiles';
    $actual = in_array($seccion, array_keys(S::SECCIONES), true) ? $seccion : (old('_seccion') ?: $primeraPendiente);
    // Valor de un campo: lo recién enviado, lo guardado en la empresa o el borrador, o la propuesta anterior
    $v = fn (string $c, $defecto = null) => old($c, $empresa->{$c} ?? ($borrador[$c] ?? ($propuestaAnterior?->{$c} ?? $defecto)));
    $fecha = fn (string $c) => old($c, $empresa->{$c}?->toDateString());
    $elegidas = array_map('intval', (array) old('carreras', $borrador['carreras'] ?? ($propuestaAnterior ? $propuestaAnterior->dictamenes->pluck('carrera_id')->all() : [])));
    $carrerasPorDivision = $carreras->groupBy(fn ($c) => $c->division->nombre);
    $faltanTotal = collect($faltantes)->flatten()->count();
    $nombres = S::atributos();
    $ast = '<span class="ml-0.5 text-rose-500" aria-hidden="true">*</span>';
    // Campo que falta para enviar: se resalta en ámbar
    $falta = fn (string $seccionCampo, string $c) => in_array($c, $faltantes[$seccionCampo] ?? [], true) ? '!border-amber-300 bg-amber-50/40' : '';
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" x-data="{ seccion: @js($actual) }" aria-labelledby="titulo-solicitud">
    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="titulo-solicitud" class="font-display text-base font-bold text-gray-900">{{ $puedeRenovar ? 'Solicitud de renovación' : ($propuestaAnterior ? 'Nueva propuesta de convenio' : 'Solicitud de convenio con el CUCSH') }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">Llénala por secciones y guarda tu avance cuando quieras: lo capturado no se pierde. <span class="text-rose-500">*</span> Obligatorio para enviar.</p>
            </div>
            <span class="text-xs font-semibold text-gray-700">{{ $completas->count() }} de 4 secciones completas</span>
        </div>

        <ol class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4" role="tablist">
            @foreach (S::SECCIONES as $clave => $nombre)
                @php $lista = empty($faltantes[$clave]); @endphp
                <li>
                    <button type="button" role="tab" x-on:click="seccion = '{{ $clave }}'" :aria-selected="(seccion === '{{ $clave }}').toString()"
                        class="flex w-full items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25"
                        :class="seccion === '{{ $clave }}' ? 'border-udg-800 bg-udg-50/70 ring-1 ring-udg-800' : 'border-gray-200 hover:border-udg-200 hover:bg-gray-50'">
                        <span @class(['flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            'bg-emerald-600 text-white' => $lista, 'bg-gray-100 text-gray-500' => !$lista])>
                            @if ($lista)<x-dashboard.icono nombre="check" clase="size-4" />@else{{ $loop->iteration }}@endif
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold leading-tight text-gray-900">{{ $nombre }}</span>
                            <span @class(['block text-[11px]', 'text-emerald-700' => $lista, 'text-amber-700' => !$lista])>{{ $lista ? 'Completa' : 'Faltan ' . count($faltantes[$clave]) }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Qué le falta a la sección abierta --}}
    @foreach (['generales', 'legales', 'perfiles'] as $clave)
        @if (!empty($faltantes[$clave]))
            <div x-show="seccion === '{{ $clave }}'" x-cloak class="mx-5 mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 sm:mx-6">
                <p class="font-semibold">Para enviar la solicitud te falta:</p>
                <p class="mt-0.5">{{ collect($faltantes[$clave])->map(fn ($c) => $nombres[preg_replace('/\.\d+$/', '', $c)] ?? $c)->unique()->join(', ', ' y ') }}.</p>
            </div>
        @endif
    @endforeach

    {{-- 1. Datos generales y fiscales --}}
    <form x-show="seccion === 'generales'" x-cloak method="POST" action="{{ route('empresa.convenio.seccion', 'generales') }}" class="space-y-6 p-5 sm:p-6"
        x-data="codigoPostal(@js((string) $v('codigo_postal', '')), @js($v('colonia')), @js($v('entidad')))">
        @csrf @method('PUT')
        <input type="hidden" name="_seccion" value="generales">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="etiqueta" for="g-razon">Razón social{!! $ast !!}</label>
                <input id="g-razon" name="razon_social" required minlength="3" maxlength="{{ F::MAX_RAZON_SOCIAL }}" data-contador="caracteres" value="{{ $v('razon_social') }}"
                    placeholder="Ej. Despacho Jurídico González S.C." class="campo h-11">
                <p class="ayuda">Completa, tal como aparece en tu constancia de situación fiscal. RFC: <span class="font-mono font-semibold text-gray-700">{{ $empresa->rfc }}</span></p>
            </div>
            <div>
                <label class="etiqueta" for="g-giro">Giro o actividad principal{!! $ast !!}</label>
                <input id="g-giro" name="giro" minlength="3" maxlength="120" value="{{ $v('giro') }}" placeholder="Ej. Despacho jurídico, museo, dependencia estatal" class="campo h-11 {{ $falta('generales', 'giro') }}">
            </div>
            <div class="md:col-span-2">
                <label class="etiqueta" for="g-desc">Datos generales de la institución{!! $ast !!}</label>
                <textarea id="g-desc" name="descripcion" rows="3" minlength="30" maxlength="1500" data-contador="caracteres" class="campo {{ $falta('generales', 'descripcion') }}"
                    placeholder="Ej. Somos un despacho fundado en 2005 que asesora a pequeñas empresas en materia civil y mercantil; atendemos a más de 200 clientes al año.">{{ $v('descripcion') }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="etiqueta" for="g-proy">Principales proyectos, acciones o clientes{!! $ast !!}</label>
                <textarea id="g-proy" name="proyectos_principales" rows="2" minlength="10" maxlength="1500" class="campo {{ $falta('generales', 'proyectos_principales') }}"
                    placeholder="Ej. Asesoría legal a la Cámara de Comercio de Guadalajara; programa de mediación vecinal.">{{ $v('proyectos_principales') }}</textarea>
            </div>
        </div>

        <fieldset class="rounded-xl border border-gray-200 p-4">
            <legend class="px-1 text-sm font-semibold text-gray-900">Domicilio fiscal</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label class="etiqueta" for="g-cp">Código postal{!! $ast !!}</label>
                    <input id="g-cp" name="codigo_postal" x-model="cp" x-on:input.debounce.400ms="buscar()" inputmode="numeric" data-solo="digitos" minlength="5" maxlength="5" pattern="\d{5}"
                        data-msg-formato="El código postal son 5 números." placeholder="Ej. 44100" class="campo h-11 {{ $falta('generales', 'codigo_postal') }}">
                    <p class="ayuda" x-text="mensaje">Escríbelo y llenamos colonia y estado.</p>
                </div>
                <div class="sm:col-span-4">
                    <label class="etiqueta" for="g-colonia">Colonia{!! $ast !!}</label>
                    <input id="g-colonia" name="colonia" x-model="colonia" list="lista-colonias" minlength="3" maxlength="120" class="campo h-11 {{ $falta('generales', 'colonia') }}" placeholder="Ej. Centro (elige de la lista o escríbela)">
                    <datalist id="lista-colonias"><template x-for="c in colonias" :key="c"><option :value="c"></option></template></datalist>
                </div>
                <div class="sm:col-span-4">
                    <label class="etiqueta" for="g-calle">Calle{!! $ast !!}</label>
                    <input id="g-calle" name="calle" minlength="3" maxlength="120" value="{{ $v('calle') }}" placeholder="Ej. Av. Juárez" class="campo h-11 {{ $falta('generales', 'calle') }}">
                </div>
                <div>
                    <label class="etiqueta" for="g-ext">Núm. exterior{!! $ast !!}</label>
                    <input id="g-ext" name="numero_exterior" maxlength="15" pattern="[A-Za-z0-9\-\/ ]+" data-msg-formato="Solo letras, números, guion o diagonal (ej. 976 o 12-B)." value="{{ $v('numero_exterior') }}" class="campo h-11 {{ $falta('generales', 'numero_exterior') }}" placeholder="Ej. 976">
                </div>
                <div>
                    <label class="etiqueta" for="g-int">Núm. interior</label>
                    <input id="g-int" name="numero_interior" maxlength="15" pattern="[A-Za-z0-9\-\/ ]+" data-msg-formato="Solo letras, números, guion o diagonal." value="{{ $v('numero_interior') }}" class="campo h-11" placeholder="Ej. 3-A">
                </div>
                <div class="sm:col-span-3">
                    <label class="etiqueta" for="g-mun">Municipio{!! $ast !!}</label>
                    <input id="g-mun" name="municipio" data-solo="letras" minlength="3" maxlength="120" value="{{ $v('municipio') }}" class="campo h-11 {{ $falta('generales', 'municipio') }}" placeholder="Ej. Guadalajara">
                </div>
                <div class="sm:col-span-3">
                    <label class="etiqueta" for="g-ent">Estado{!! $ast !!}</label>
                    <select id="g-ent" name="entidad" x-model="entidad" class="campo h-11 {{ $falta('generales', 'entidad') }}">
                        <option value="">Elige el estado</option>
                        @foreach (S::ENTIDADES as $e)<option value="{{ $e }}">{{ $e }}</option>@endforeach
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="rounded-xl border border-gray-200 p-4">
            <legend class="px-1 text-sm font-semibold text-gray-900">¿Quién propone el convenio?</legend>
            <p class="mb-3 text-xs text-gray-500">Nombre completo y filiación institucional de quien dará seguimiento (por ejemplo, el enlace de Recursos Humanos).</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="etiqueta" for="g-cnom">Nombre completo{!! $ast !!}</label>
                    <input id="g-cnom" name="contacto_nombre" required data-solo="letras" minlength="5" maxlength="{{ F::MAX_NOMBRE_PERSONA }}" pattern="{{ F::NOMBRE_PERSONA_HTML }}"
                        data-msg-formato="Escribe nombre y apellido, solo con letras." value="{{ $v('contacto_nombre') }}" placeholder="Ej. Laura Gómez Ruiz" class="campo h-11">
                </div>
                <div>
                    <label class="etiqueta" for="g-ccar">Cargo o área{!! $ast !!}</label>
                    <input id="g-ccar" name="contacto_cargo" data-solo="letras" minlength="3" maxlength="120" value="{{ $v('contacto_cargo') }}" placeholder="Ej. Coordinadora de Recursos Humanos" class="campo h-11 {{ $falta('generales', 'contacto_cargo') }}">
                </div>
                <div>
                    <label class="etiqueta" for="g-ctel">Teléfono{!! $ast !!}</label>
                    <input id="g-ctel" name="contacto_telefono" type="tel" inputmode="numeric" data-solo="digitos" data-contador="dígitos" minlength="10" maxlength="10" pattern="\d{10}"
                        data-msg-formato="El teléfono son 10 números." value="{{ $v('contacto_telefono') }}" placeholder="Ej. 3312345678" class="campo h-11 {{ $falta('generales', 'contacto_telefono') }}">
                </div>
            </div>
        </fieldset>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button class="boton boton-primario"><x-dashboard.icono nombre="check" clase="size-4" /> Guardar y continuar</button>
        </div>
    </form>

    {{-- 2. Declaraciones legales --}}
    <form x-show="seccion === 'legales'" x-cloak method="POST" action="{{ route('empresa.convenio.seccion', 'legales') }}" class="space-y-6 p-5 sm:p-6"
        x-data="{ tipo: @js($empresa->tipo_organismo->value), origen: @js($v('facultad_origen', $escritura ? '' : 'nombramiento')) }">
        @csrf @method('PUT')
        <input type="hidden" name="_seccion" value="legales">
        <p class="rounded-xl bg-udg-50/60 px-4 py-3 text-sm text-udg-900 ring-1 ring-inset ring-udg-100">Son las declaraciones de tu organización en el convenio: van tal cual al documento y Vinculación las compara con tus documentos.</p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="etiqueta" for="l-rep">Representante legal (quien firma){!! $ast !!}</label>
                <input id="l-rep" name="representante_legal" data-solo="letras" minlength="5" maxlength="{{ F::MAX_NOMBRE_PERSONA }}" pattern="{{ F::NOMBRE_PERSONA_HTML }}"
                    data-msg-formato="Escribe nombre y apellido del representante, solo con letras." value="{{ $v('representante_legal') }}" placeholder="Ej. Lic. Jorge Ramírez Lozano" class="campo h-11 {{ $falta('legales', 'representante_legal') }}">
            </div>
            <div>
                <label class="etiqueta" for="l-cargo">Cargo del representante{!! $ast !!}</label>
                <input id="l-cargo" name="representante_cargo" data-solo="letras" minlength="3" maxlength="120" value="{{ $v('representante_cargo') }}" placeholder="Ej. Director General" class="campo h-11 {{ $falta('legales', 'representante_cargo') }}">
            </div>
            <div class="md:col-span-2">
                <label class="etiqueta" for="l-obj">Objeto social o atribuciones relacionadas con las prácticas{!! $ast !!}</label>
                <textarea id="l-obj" name="objeto_social" rows="2" minlength="20" maxlength="1500" class="campo {{ $falta('legales', 'objeto_social') }}"
                    placeholder="Ej. Prestar servicios de asesoría jurídica en materia civil, mercantil y familiar.">{{ $v('objeto_social') }}</textarea>
            </div>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-900">{{ $escritura ? 'Acta constitutiva' : 'Documento de creación' }}{!! $ast !!}</legend>
            <x-empresa.datos-notariales :empresa="$empresa" />
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-900">Facultades de quien firma{!! $ast !!}</legend>
            @if ($escritura)
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-gray-200 p-3 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-50/60">
                        <input type="radio" name="facultad_origen" value="acta" x-model="origen" class="mt-0.5">
                        <span class="text-sm"><span class="font-semibold text-gray-900">Vienen del acta constitutiva</span><span class="block text-xs text-gray-500">El representante aparece en el acta con facultades para firmar.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-gray-200 p-3 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-50/60">
                        <input type="radio" name="facultad_origen" value="poder" x-model="origen" class="mt-0.5">
                        <span class="text-sm"><span class="font-semibold text-gray-900">Vienen de un poder notarial</span><span class="block text-xs text-gray-500">Captura sus datos y sube el poder en Documentos.</span></span>
                    </label>
                </div>
                <div x-show="origen === 'poder'" x-cloak class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta" for="p-num">Número de escritura del poder{!! $ast !!}</label>
                        <input id="p-num" name="poder_escritura_numero" inputmode="numeric" data-solo="digitos" maxlength="10" value="{{ $v('poder_escritura_numero') }}" placeholder="Ej. 5678" :disabled="origen !== 'poder'" class="campo h-11">
                    </div>
                    <div>
                        <label class="etiqueta" for="p-fecha">Fecha del poder{!! $ast !!}</label>
                        <input id="p-fecha" type="date" name="poder_fecha" max="{{ today()->toDateString() }}" value="{{ $fecha('poder_fecha') }}" :disabled="origen !== 'poder'" class="campo h-11">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="etiqueta" for="p-not">Notario que dio fe{!! $ast !!}</label>
                        <input id="p-not" name="poder_notario_nombre" data-solo="letras" minlength="5" maxlength="{{ F::MAX_NOMBRE_PERSONA }}" value="{{ $v('poder_notario_nombre') }}" placeholder="Ej. Lic. Ricardo Alatorre Gómez" :disabled="origen !== 'poder'" class="campo h-11">
                    </div>
                    <div>
                        <label class="etiqueta" for="p-nota">Número de notaría{!! $ast !!}</label>
                        <input id="p-nota" name="poder_notaria_numero" inputmode="numeric" data-solo="digitos" maxlength="4" value="{{ $v('poder_notaria_numero') }}" placeholder="Ej. 12" :disabled="origen !== 'poder'" class="campo h-11">
                    </div>
                    <div>
                        <label class="etiqueta" for="p-ciu">Ciudad de la notaría{!! $ast !!}</label>
                        <input id="p-ciu" name="poder_notaria_ciudad" data-solo="letras" maxlength="120" value="{{ $v('poder_notaria_ciudad') }}" placeholder="Ej. Guadalajara, Jalisco" :disabled="origen !== 'poder'" class="campo h-11">
                    </div>
                </div>
            @else
                <input type="hidden" name="facultad_origen" value="nombramiento">
                <label class="etiqueta" for="l-nomb">Nombramiento del titular{!! $ast !!}</label>
                <textarea id="l-nomb" name="facultad_firmante" rows="2" minlength="15" maxlength="1500" class="campo {{ $falta('legales', 'facultad_firmante') }}" placeholder="Ej. Nombramiento de fecha 1 de enero de 2025 expedido por el Gobernador del Estado">{{ $v('facultad_firmante') }}</textarea>
                <p class="ayuda">Sube el nombramiento oficial vigente en Documentos.</p>
            @endif
        </fieldset>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button class="boton boton-primario"><x-dashboard.icono nombre="check" clase="size-4" /> Guardar y continuar</button>
        </div>
    </form>

    {{-- 3. Perfiles requeridos --}}
    <form x-show="seccion === 'perfiles'" x-cloak method="POST" action="{{ route('empresa.convenio.seccion', 'perfiles') }}" class="space-y-5 p-5 sm:p-6"
        x-data="{ elegidas: @js($elegidas) }">
        @csrf @method('PUT')
        <input type="hidden" name="_seccion" value="perfiles">
        <fieldset>
            <legend class="etiqueta">¿Qué carreras solicitan para prácticas profesionales?{!! $ast !!} <span class="font-normal text-gray-500" x-text="elegidas.length ? '· ' + elegidas.length + (elegidas.length === 1 ? ' elegida' : ' elegidas') : ''"></span></legend>
            <div class="space-y-4 rounded-xl border border-gray-200 p-4">
                @foreach ($carrerasPorDivision as $division => $lista)
                    <div>
                        <p class="mb-2 text-xs font-semibold text-gray-500">{{ $division }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($lista as $c)
                                <label class="cursor-pointer select-none rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 transition hover:border-udg-200 hover:bg-udg-50 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-800 has-[:checked]:text-white has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-real-500/25">
                                    <input type="checkbox" name="carreras[]" value="{{ $c->id }}" class="sr-only" x-model.number="elegidas">
                                    {{ $c->nombre }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="etiqueta" for="c-obj">¿Qué objetivos se cubrirán con las prácticas?{!! $ast !!}</label>
                <textarea id="c-obj" name="objetivos" rows="4" minlength="20" maxlength="3000" class="campo {{ $falta('perfiles', 'objetivos') }}"
                    placeholder="Ej. Que el practicante aplique técnicas de investigación jurídica y redacción de escritos, en línea con el plan de estudios de Abogado.">{{ $v('objetivos') }}</textarea>
                <p class="ayuda">Empata los objetivos de tu organización con el plan de estudios de la carrera.</p>
            </div>
            <div>
                <label class="etiqueta" for="c-act">Enumere las actividades que realizarán los practicantes{!! $ast !!}</label>
                <textarea id="c-act" name="actividades_propuestas" rows="4" minlength="30" maxlength="3000" class="campo {{ $falta('perfiles', 'actividades_propuestas') }}"
                    placeholder="Ej. 1) Revisión de expedientes. 2) Redacción de escritos. 3) Atención a clientes con supervisión.">{{ $v('actividades_propuestas') }}</textarea>
                <p class="ayuda">Van tal cual al convenio (cláusula de actividades).</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label class="etiqueta" for="c-plazas">¿Cuántos lugares (plazas) ofrecen por semestre?{!! $ast !!}</label>
                <input id="c-plazas" type="number" name="plazas_estimadas" value="{{ $v('plazas_estimadas') }}" min="1" max="500" placeholder="Ej. 3" class="campo h-11 tabular-nums {{ $falta('perfiles', 'plazas_estimadas') }}">
            </div>
            <div class="md:col-span-2">
                <label class="etiqueta" for="c-medio">¿Cómo se enteraron de la posibilidad de tener practicantes del CUCSH?{!! $ast !!}</label>
                <input id="c-medio" name="medio_conocimiento" value="{{ $v('medio_conocimiento') }}" minlength="3" maxlength="255" placeholder="Ej. Por la página del CUCSH, por un egresado o en una feria de empleo" class="campo h-11 {{ $falta('perfiles', 'medio_conocimiento') }}">
            </div>
        </div>
        <div>
            <label class="etiqueta" for="c-exp">¿Cuántos practicantes han recibido en sus oficinas? ¿Desde qué fecha?{!! $ast !!}</label>
            <textarea id="c-exp" name="experiencia_previa" rows="2" minlength="2" maxlength="3000" class="campo {{ $falta('perfiles', 'experiencia_previa') }}" placeholder="Ej. 6 practicantes desde 2022. Si es la primera vez, escribe «Ninguno»">{{ $v('experiencia_previa') }}</textarea>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
            <button class="boton boton-primario"><x-dashboard.icono nombre="check" clase="size-4" /> Guardar y continuar</button>
        </div>
    </form>

    {{-- 4. Documentos --}}
    <div x-show="seccion === 'documentos'" x-cloak class="space-y-4 p-5 sm:p-6">
        <p class="text-sm text-gray-600">Sube tu expediente en <span class="font-semibold">PDF (máximo {{ \App\Models\Empresa::MAX_MB }} MB cada uno)</span>. Al terminar regresarás aquí para enviar tu solicitud.</p>
        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200">
            @foreach ($empresa->obligatorias() as $col)
                @php $m = $empresa->documentoVigente($col); $st = $m ? ($m->getCustomProperty('estado') ?? 'pendiente') : null; @endphp
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="text-sm text-gray-800">{{ \App\Models\Empresa::nombreDocumento($col) }}<span class="ml-0.5 text-rose-500">*</span></span>
                    <x-ui.estatus :familia="['validada' => 'menta', 'rechazada' => 'rosa', 'pendiente' => 'cielo'][$st] ?? 'ambar'"
                        :texto="['validada' => 'Validado', 'rechazada' => 'Devuelto', 'pendiente' => 'Subido'][$st] ?? 'Falta subirlo'" />
                </li>
            @endforeach
        </ul>
        <div class="flex justify-end"><a href="{{ route('empresa.documentos') }}" class="boton boton-primario"><x-dashboard.icono nombre="subir" clase="size-4" /> {{ empty($faltantes['documentos']) ? 'Revisar mis documentos' : 'Subir mis documentos' }}</a></div>
    </div>

    {{-- Envío --}}
    <form method="POST" action="{{ route('empresa.convenio.solicitar') }}" @class(['flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6',
        'border-gray-100 bg-gray-50/60' => $faltanTotal, 'border-emerald-100 bg-emerald-50/70' => !$faltanTotal])>
        @csrf
        <p class="text-sm {{ $faltanTotal ? 'text-gray-600' : 'font-semibold text-emerald-800' }}">
            {{ $faltanTotal ? 'Cuando las 4 secciones estén completas podrás enviar la solicitud a Vinculación.' : '¡Todo listo! Envía tu solicitud a Vinculación.' }}
        </p>
        @if ($faltanTotal || $motivoSolicitud)
            <x-ui.accion-bloqueada :motivo="$faltanTotal ? 'Faltan ' . $faltanTotal . ' dato(s) o documento(s) en las secciones marcadas.' : $motivoSolicitud">{{ $puedeRenovar ? 'Enviar renovación' : 'Enviar solicitud' }}</x-ui.accion-bloqueada>
        @else
            <button class="boton boton-primario shrink-0"><x-dashboard.icono nombre="enviar" clase="size-4" /> {{ $puedeRenovar ? 'Enviar renovación' : 'Enviar solicitud' }}</button>
        @endif
    </form>
</section>
