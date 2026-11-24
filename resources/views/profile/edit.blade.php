@extends('layouts.app')

@section('content')
@php
    use App\Enums\Rol;

    $estado = session('status');
    $rolEtiqueta = $rol?->label() ?? 'Usuario';

    // Sección del rol (la tercera pestaña cambia según quién eres)
    $seccionRol = match ($rol) {
        Rol::Alumno => isset($alumno) ? ['clave' => 'escolar', 'titulo' => 'Datos escolares', 'icono' => 'academico'] : null,
        Rol::Empresa => isset($empresa) ? ['clave' => 'organizacion', 'titulo' => 'Mi organización', 'icono' => 'empresa'] : null,
        Rol::Coordinador => isset($personal) ? ['clave' => 'coordinacion', 'titulo' => 'Mi coordinación', 'icono' => 'usuarios'] : null,
        Rol::Vinculacion => ['clave' => 'area', 'titulo' => 'Mi área', 'icono' => 'documento'],
        Rol::Administrador => ['clave' => 'administracion', 'titulo' => 'Administración', 'icono' => 'informe'],
        default => null,
    };

    $secciones = array_values(array_filter([
        ['clave' => 'cuenta', 'titulo' => 'Mi cuenta', 'icono' => 'alumno', 'texto' => 'Nombre y correo'],
        $seccionRol ? $seccionRol + ['texto' => match ($seccionRol['clave']) {
            'escolar' => 'Grado, turno y requisitos',
            'organizacion' => 'Contacto y presentación',
            'coordinacion' => 'Carreras a tu cargo',
            'area' => 'Lo que atiendes',
            default => 'Accesos del sistema',
        }] : null,
        ['clave' => 'seguridad', 'titulo' => 'Seguridad', 'icono' => 'vigente', 'texto' => 'Contraseña de acceso'],
        ['clave' => 'avisos', 'titulo' => 'Avisos y privacidad', 'icono' => 'alerta', 'texto' => 'Qué te notificamos'],
    ]));
    $claves = array_column($secciones, 'clave');

    // Pestaña inicial: la que pide la URL o la que tiene errores / mensaje de éxito
    $inicial = match (true) {
        $errors->updatePassword->isNotEmpty() || $estado === 'password-updated' => 'seguridad',
        $errors->organizacion->isNotEmpty() || $errors->has('representante_legal') || $estado === 'organizacion-updated' || $estado === 'Representante legal actualizado.' => 'organizacion',
        $errors->hasAny(['grado', 'turno']) || $estado === 'datos-escolares-updated' => 'escolar',
        $errors->hasAny(['name', 'email', 'foto']) => 'cuenta',
        in_array(request('seccion'), $claves, true) => request('seccion'),
        default => 'cuenta',
    };

    $mensajes = [
        'profile-updated' => 'Tus datos se actualizaron.',
        'password-updated' => 'Tu contraseña se actualizó.',
        'foto-updated' => 'Tu foto se actualizó.',
        'foto-deleted' => 'Quitaste tu foto de perfil.',
        'datos-escolares-updated' => 'Tus datos escolares se actualizaron.',
        'organizacion-updated' => 'Los datos de tu organización se actualizaron.',
    ];
    $mensaje = $mensajes[$estado] ?? ($estado && !str_contains((string) $estado, '-') ? $estado : null);
@endphp

<div class="space-y-6"
    x-data="{
        tab: @js($inicial),
        ir(t) { this.tab = t; const u = new URL(location); u.searchParams.set('seccion', t); history.replaceState(null, '', u); },
    }">

    {{-- Tarjeta de identidad --}}
    <header class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-udg-950 via-udg-900 to-udg-800 px-5 py-6 text-white shadow-theme-md sm:px-8 sm:py-8">
        <div class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-oro-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="absolute inset-x-0 bottom-0 h-1 bg-oro-400" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center"
            x-data="{ subiendo: false, elegir() { if (this.$refs.foto.files.length) { this.subiendo = true; this.$refs.formFoto.submit(); } } }">
            {{-- Foto: al elegir la imagen se guarda en el momento --}}
            <form method="POST" action="{{ route('profile.foto') }}" enctype="multipart/form-data" x-ref="formFoto" class="hidden">
                @csrf
                <input id="foto" x-ref="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" @change="elegir()">
            </form>
            <button type="button" @click="$refs.foto.click()"
                class="group relative size-24 shrink-0 self-start rounded-full ring-4 ring-white/20 transition focus-visible:outline-none focus-visible:ring-oro-300 sm:self-center"
                aria-label="Cambiar foto de perfil">
                <x-ui.user-avatar :user="$user" size="size-24 text-2xl" />
                <span class="absolute inset-0 flex items-center justify-center rounded-full bg-udg-950/60 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" :class="subiendo && '!opacity-100'">
                    <svg x-show="!subiendo" class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    <svg x-show="subiendo" x-cloak class="size-7 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                </span>
            </button>
            <div class="min-w-0 flex-1 animate-entrada motion-reduce:animate-none">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-300">Mi perfil · {{ $rolEtiqueta }}</p>
                <h1 class="mt-1 break-words font-display text-2xl font-bold tracking-[-0.02em] sm:text-3xl">{{ $user->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-white/80">
                    <span class="break-all">{{ $user->email }}</span>
                    @if ($user->hasVerifiedEmail())
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2 py-0.5 text-xs font-semibold text-emerald-200 ring-1 ring-inset ring-emerald-300/30">
                            <x-dashboard.icono nombre="check" clase="size-3" /> Verificado
                        </span>
                    @else
                        <a href="{{ route('verification.notice') }}" class="inline-flex items-center gap-1 rounded-full bg-amber-400/15 px-2 py-0.5 text-xs font-semibold text-amber-200 ring-1 ring-inset ring-amber-300/30 hover:bg-amber-400/25">Sin verificar · reenviar</a>
                    @endif
                </div>
                <p class="mt-2 text-xs text-white/60">
                    @if (isset($alumno)) {{ $alumno->carrera->nombre }} · Código {{ $alumno->num_control }} ·
                    @elseif (isset($empresa)) {{ $empresa->razon_social }} ·
                    @elseif (isset($personal) && $personal->puesto) {{ $personal->puesto }} ·
                    @endif
                    Miembro desde {{ $user->created_at?->translatedFormat('F \d\e Y') }}
                </p>
                @error('foto')<p class="mt-2 text-sm font-medium text-rose-200" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-stretch">
                <button type="button" @click="$refs.foto.click()" :disabled="subiendo"
                    class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50 disabled:cursor-wait">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    <span x-text="subiendo ? 'Guardando…' : 'Cambiar foto'">Cambiar foto</span>
                </button>
                @if ($user->foto_url)
                    <form method="POST" action="{{ route('profile.foto.eliminar') }}" data-confirmar="¿Quitar tu foto de perfil? Se mostrarán tus iniciales en su lugar." data-confirmar-titulo="Quitar foto" data-confirmar-boton="Sí, quitarla">
                        @csrf
                        @method('DELETE')
                        <button class="boton w-full border border-white/25 text-sm text-white hover:bg-white/10">Quitar foto</button>
                    </form>
                @endif
                <p class="text-[11px] text-white/50 sm:text-center">JPG, PNG o WebP · hasta 2 MB</p>
            </div>
        </div>
    </header>

    @if ($mensaje)
        <x-ui.aviso tipo="exito" descartable :autocerrar="6000">{{ $mensaje }}</x-ui.aviso>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem_1fr]">

        {{-- Navegación de secciones --}}
        <nav aria-label="Secciones del perfil" class="lg:sticky lg:top-28 lg:self-start">
            <ul class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 no-scrollbar lg:flex-col lg:overflow-visible" role="tablist">
                @foreach ($secciones as $s)
                    <li class="shrink-0">
                        <button type="button" role="tab" id="tab-{{ $s['clave'] }}" aria-controls="panel-{{ $s['clave'] }}"
                            @click="ir(@js($s['clave']))" :aria-selected="(tab === @js($s['clave'])).toString()"
                            class="group flex w-full items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition duration-150"
                            :class="tab === @js($s['clave'])
                                ? 'border-udg-200 bg-white shadow-theme-sm ring-1 ring-udg-100'
                                : 'border-transparent hover:bg-white hover:shadow-theme-xs'">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                :class="tab === @js($s['clave']) ? 'bg-udg-900 text-white' : 'bg-gray-100 text-gray-500 group-hover:text-udg-900'">
                                <x-dashboard.icono :nombre="$s['icono']" clase="size-[18px]" />
                            </span>
                            <span class="min-w-0">
                                <span class="block whitespace-nowrap text-sm font-semibold" :class="tab === @js($s['clave']) ? 'text-udg-900' : 'text-gray-700'">{{ $s['titulo'] }}</span>
                                <span class="hidden text-xs text-gray-500 lg:block">{{ $s['texto'] }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0">

            {{-- ===================== MI CUENTA ===================== --}}
            <section id="panel-cuenta" role="tabpanel" aria-labelledby="tab-cuenta" x-show="tab === 'cuenta'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                <x-dashboard.panel titulo="Datos de la cuenta" icono="documento" descripcion="Tu nombre aparece en tus trámites. Si cambias tu correo tendrás que confirmarlo de nuevo.">
                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="name" class="etiqueta">Nombre completo</label>
                                <input id="name" name="name" type="text" data-solo="letras" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+(\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+)+" data-msg-formato="Escribe nombre y apellido, solo con letras." value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="campo h-11">
                                @error('name')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="etiqueta">Correo electrónico</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="username" class="campo h-11">
                                @error('email')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button class="boton boton-primario w-full sm:w-auto">Guardar cambios</button>
                        </div>
                    </form>
                </x-dashboard.panel>
            </section>

            {{-- ===================== ALUMNO: DATOS ESCOLARES ===================== --}}
            @isset($alumno)
                <section id="panel-escolar" role="tabpanel" aria-labelledby="tab-escolar" x-show="tab === 'escolar'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                    {{-- Necesario para pruebas y lectores: título visible de la sección --}}
                    <x-dashboard.panel titulo="Datos escolares" icono="academico" descripcion="Tu grado y turno aparecen en tu carta de presentación: mantenlos al día.">
                        <dl class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach ([['Carrera', $alumno->carrera->nombre], ['Código', $alumno->num_control], ['Horas requeridas', $alumno->carrera->horas_requeridas . ' h']] as [$k, $v])
                                <div class="rounded-xl bg-gray-50 p-3.5 ring-1 ring-inset ring-gray-100">
                                    <dt class="text-xs text-gray-500">{{ $k }}</dt>
                                    <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <form method="POST" action="{{ route('alumno.datos-escolares') }}" class="space-y-5">
                            @csrf
                            @method('PUT')
                            <x-alumno.grado-turno :grado="$alumno->grado" :turno="$alumno->turno" />
                            <div class="flex justify-end"><button class="boton boton-primario w-full sm:w-auto">Guardar datos escolares</button></div>
                        </form>
                    </x-dashboard.panel>

                    <x-dashboard.panel titulo="¿Estás listo para postularte?" icono="check" descripcion="Lo que revisa el sistema antes de habilitar el botón «Postularme».">
                        @php $cumplidos = collect($requisitos)->where('ok', true)->count(); @endphp
                        <div class="mb-4 flex items-center gap-3">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $cumplidos / max(1, count($requisitos)) * 100 }}%"></div></div>
                            <span class="text-sm font-semibold tabular-nums text-gray-700">{{ $cumplidos }}/{{ count($requisitos) }}</span>
                        </div>
                        <ul class="space-y-2">
                            @foreach ($requisitos as $r)
                                <li class="flex items-start gap-3 rounded-xl p-3 {{ $r['ok'] ? 'bg-emerald-50/60' : 'bg-gray-50' }}">
                                    <span @class(['mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full', 'bg-emerald-500 text-white' => $r['ok'], 'bg-white text-gray-400 ring-1 ring-inset ring-gray-300' => !$r['ok']])>
                                        @if ($r['ok'])<x-dashboard.icono nombre="check" clase="size-3.5" />@else<span class="size-1.5 rounded-full bg-gray-300"></span>@endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold {{ $r['ok'] ? 'text-emerald-800' : 'text-gray-900' }}">{{ $r['texto'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $r['ayuda'] }}</p>
                                    </div>
                                    @unless ($r['ok'])
                                        @isset($r['ruta'])
                                            <a href="{{ route($r['ruta']) }}" class="enlace shrink-0 text-xs">Resolver →</a>
                                        @else
                                            <button type="button" @click="document.getElementById('grado')?.focus()" class="enlace shrink-0 text-xs">Capturar →</button>
                                        @endisset
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">
                            @if ($motivoPostular === null)
                                <x-ui.aviso tipo="exito" :autocerrar="false" titulo="¡Todo listo!">Ya puedes postularte. <a href="{{ route('alumno.oferta-programas') }}" class="font-semibold underline underline-offset-2">Ver programas de tu carrera</a></x-ui.aviso>
                            @else
                                <x-ui.aviso tipo="advertencia" titulo="Aún no puedes postularte">{{ $motivoPostular }}</x-ui.aviso>
                            @endif
                        </div>
                    </x-dashboard.panel>

                    <x-alumno.tu-coordinacion :carrera="$alumno->carrera" />
                </section>
            @endisset

            {{-- ===================== EMPRESA: MI ORGANIZACIÓN ===================== --}}
            @isset($empresa)
                <section id="panel-organizacion" role="tabpanel" aria-labelledby="tab-organizacion" x-show="tab === 'organizacion'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                    <x-dashboard.panel titulo="Datos legales" icono="empresa" descripcion="Para corregir la razón social, el RFC o el tipo de organismo, escribe a Vinculación." :enlace="route('empresa.convenio')" enlace-texto="Mi convenio">
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ([['Razón social', $empresa->razon_social], ['RFC', $empresa->rfc], ['Tipo de organismo', $empresa->tipo_organismo?->label() ?? '—'], ['Domicilio fiscal', $empresa->domicilio_fiscal ?: '—']] as [$k, $v])
                                <div class="rounded-xl bg-gray-50 p-3.5 ring-1 ring-inset ring-gray-100">
                                    <dt class="text-xs text-gray-500">{{ $k }}</dt>
                                    <dd class="mt-0.5 break-words text-sm font-semibold text-gray-900 {{ $k === 'RFC' ? 'font-mono' : '' }}">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($convenioVigente)
                                <x-ui.estatus familia="menta" :texto="'Convenio vigente hasta ' . $convenioVigente->fecha_fin_vigencia->format('d/m/Y')" tamano="normal" />
                            @elseif ($convenioEnTramite)
                                <x-ui.estatus :estado="$convenioEnTramite->estado" :texto="'Convenio: ' . $convenioEnTramite->estado->etiquetaEmpresa()" tamano="normal" />
                            @else
                                <x-ui.estatus familia="gris" texto="Sin convenio" tamano="normal" />
                            @endif
                            <x-ui.estatus :familia="$expedienteCompleto ? 'menta' : 'ambar'" :texto="$expedienteCompleto ? 'Expediente en orden' : 'Expediente por completar'" tamano="normal" />
                        </div>
                    </x-dashboard.panel>

                    <x-dashboard.panel titulo="Contacto y presentación" icono="comentario" descripcion="Lo ve Vinculación y la coordinación de cada carrera al revisar sus vacantes.">
                        <form method="POST" action="{{ route('empresa.perfil.update') }}" class="space-y-5">
                            @csrf
                            @method('PATCH')
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                                <div class="md:col-span-1">
                                    <label for="contacto_nombre" class="etiqueta">Persona de contacto</label>
                                    <input id="contacto_nombre" data-solo="letras" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+(\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+)+" data-msg-formato="Escribe nombre y apellido, solo con letras." name="contacto_nombre" value="{{ old('contacto_nombre', $empresa->contacto_nombre) }}" required maxlength="255" class="campo h-11">
                                    @error('contacto_nombre', 'organizacion')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="contacto_cargo" class="etiqueta">Cargo</label>
                                    <input id="contacto_cargo" data-solo="letras" name="contacto_cargo" value="{{ old('contacto_cargo', $empresa->contacto_cargo) }}" maxlength="255" class="campo h-11">
                                </div>
                                <div>
                                    <label for="contacto_telefono" class="etiqueta">Teléfono</label>
                                    <input id="contacto_telefono" name="contacto_telefono" type="tel" inputmode="numeric" autocomplete="tel" data-solo="digitos" data-contador="dígitos" minlength="10" maxlength="10" pattern="\d{10}" data-msg-formato="El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3312345678)." value="{{ old('contacto_telefono', $empresa->contacto_telefono) }}" class="campo h-11">
                                    @error('contacto_telefono', 'organizacion')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            @foreach ([['descripcion', '¿A qué se dedica su organización?'], ['proyectos_principales', 'Proyectos principales']] as [$campo, $etiqueta])
                                <div x-data="{ n: {{ mb_strlen(old($campo, $empresa->{$campo}) ?? '') }} }">
                                    <label for="{{ $campo }}" class="etiqueta">{{ $etiqueta }}</label>
                                    <textarea id="{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="3000" @input="n = $event.target.value.length" class="campo">{{ old($campo, $empresa->{$campo}) }}</textarea>
                                    <p class="ayuda text-right"><span x-text="n"></span>/3000</p>
                                </div>
                            @endforeach
                            <div class="flex justify-end"><button class="boton boton-primario w-full sm:w-auto">Guardar datos de la organización</button></div>
                        </form>
                    </x-dashboard.panel>

                    <x-dashboard.panel titulo="Representante legal" icono="firma" descripcion="Firma el convenio en nombre de su organización.">
                        @if ($representanteBloqueado)
                            <x-ui.aviso tipo="info" titulo="{{ $empresa->representante_legal ?: 'Sin registrar' }}">Su convenio está en revisión: podrá cambiar al representante legal cuando termine.</x-ui.aviso>
                        @else
                            <form method="POST" action="{{ route('empresa.datos-legales') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                @csrf
                                @method('PUT')
                                <div class="flex-1">
                                    <label for="representante_legal" class="etiqueta">Nombre completo</label>
                                    <input id="representante_legal" name="representante_legal" value="{{ old('representante_legal', $empresa->representante_legal) }}" required maxlength="255" class="campo h-11">
                                    @error('representante_legal')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                                </div>
                                <button class="boton boton-primario">Actualizar</button>
                            </form>
                        @endif
                    </x-dashboard.panel>
                </section>
            @endisset

            {{-- ===================== COORDINACIÓN ===================== --}}
            @isset($carreras)
                <section id="panel-coordinacion" role="tabpanel" aria-labelledby="tab-coordinacion" x-show="tab === 'coordinacion'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                    <x-dashboard.panel titulo="Carreras a tu cargo" icono="usuarios"
                        :descripcion="$personal->division_id ? 'Alcance por división: ' . $personal->division?->nombre : 'Alcance por carrera'"
                        :enlace="route('coordinacion.reglas')" enlace-texto="Editar reglas">
                        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach ($carreras as $c)
                                <li class="rounded-xl border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-semibold leading-snug text-gray-900">{{ $c->nombre }}</p>
                                        <span class="shrink-0 rounded-md bg-udg-50 px-2 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $c->carrera_clave }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $c->division?->nombre }}</p>
                                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                                        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Horas</dt><dd class="font-display font-bold tabular-nums text-gray-900">{{ $c->horas_requeridas }}</dd></div>
                                        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Créditos</dt><dd class="font-display font-bold tabular-nums text-gray-900">{{ rtrim(rtrim(number_format((float) $c->porcentaje_creditos_minimo, 2), '0'), '.') }}%</dd></div>
                                        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Reportes</dt><dd class="truncate text-xs font-semibold text-gray-900" title="{{ $c->esquema_reportes?->label() }}">{{ $c->esquema_reportes ? \Illuminate\Support\Str::before($c->esquema_reportes->label(), ' +') : '—' }}</dd></div>
                                    </dl>
                                    <div class="mt-3 border-t border-gray-100 pt-3 text-xs">
                                        <p class="font-semibold uppercase tracking-wide text-gray-500">Contacto que ven los alumnos</p>
                                        @if ($c->coordinacion_correo || $c->coordinacion_telefono)
                                            <p class="mt-1 break-all text-gray-700">{{ $c->coordinacion_correo ?: 'Sin correo' }}{{ $c->coordinacion_telefono ? ' · ' . $c->coordinacion_telefono : '' }}</p>
                                        @else
                                            <p class="mt-1 text-amber-700">Sin registrar: los alumnos no tienen a quién escribir. <a href="{{ route('coordinacion.reglas') }}" class="font-semibold underline">Agrégalo</a></p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-dashboard.panel>
                </section>
            @endisset

            {{-- ===================== VINCULACIÓN ===================== --}}
            @if ($rol === Rol::Vinculacion)
                <section id="panel-area" role="tabpanel" aria-labelledby="tab-area" x-show="tab === 'area'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                    <x-dashboard.panel titulo="Vinculación · Prácticas Profesionales" icono="documento" :descripcion="($personal->puesto ?? null) ? 'Puesto: ' . $personal->puesto : 'Trámite de convenios con organizaciones'">
                        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['documento', 'Expedientes de empresas', 'Valida los documentos de cada organización.', 'admin.lista-empresas'],
                                ['academico', 'Validación académica', 'Turna a Secretaría/División y da seguimiento a los dictámenes.', 'admin.lista-dictamenes'],
                                ['juridico', 'Unidad Jurídica y firmas', 'Registra el dictamen jurídico y las firmas.', 'admin.lista-convenios'],
                                ['informe', 'Informes de convenios', 'Exporta el estado de los convenios.', 'admin.informes'],
                            ] as [$ic, $t, $d, $r])
                                <a href="{{ route($r) }}" class="group flex gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-udg-200 hover:shadow-theme-sm">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100"><x-dashboard.icono :nombre="$ic" /></span>
                                    <span><span class="block text-sm font-semibold text-gray-900 group-hover:text-udg-900">{{ $t }}</span><span class="text-xs text-gray-500">{{ $d }}</span></span>
                                </a>
                            @endforeach
                        </ul>
                        <div class="mt-4 rounded-xl bg-oro-50 p-4 ring-1 ring-inset ring-oro-100">
                            <p class="text-xs font-semibold uppercase tracking-wide text-oro-700">Buzón institucional</p>
                            <p class="mt-1 break-all text-sm font-semibold text-gray-900">{{ config('practicas.contacto.correo') }}</p>
                            <p class="mt-0.5 text-xs text-gray-600">Recibe copia de cada nueva solicitud de convenio. Es el correo público de Vinculación en el sitio.</p>
                        </div>
                    </x-dashboard.panel>
                </section>
            @endif

            {{-- ===================== ADMINISTRADOR ===================== --}}
            @if ($rol === Rol::Administrador)
                <section id="panel-administracion" role="tabpanel" aria-labelledby="tab-administracion" x-show="tab === 'administracion'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                    <x-dashboard.panel titulo="Administrador" icono="informe" descripcion="Entras a todo: Vinculación, coordinaciones y administración del sistema.">
                        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach ([
                                ['usuarios', 'Usuarios y coordinadores', 'Altas de personal y contraseñas.', 'admin.lista-usuarios'],
                                ['academico', 'Divisiones y carreras', 'Catálogo académico.', 'admin.catalogos'],
                                ['reloj', 'Auditoría', 'Bitácora de acciones.', 'admin.auditoria'],
                            ] as [$ic, $t, $d, $r])
                                <a href="{{ route($r) }}" class="group flex flex-col gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-udg-200 hover:shadow-theme-sm">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100"><x-dashboard.icono :nombre="$ic" /></span>
                                    <span><span class="block text-sm font-semibold text-gray-900 group-hover:text-udg-900">{{ $t }}</span><span class="text-xs text-gray-500">{{ $d }}</span></span>
                                </a>
                            @endforeach
                        </ul>
                    </x-dashboard.panel>
                </section>
            @endif

            {{-- ===================== SEGURIDAD ===================== --}}
            <section id="panel-seguridad" role="tabpanel" aria-labelledby="tab-seguridad" x-show="tab === 'seguridad'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                <x-dashboard.panel titulo="Cambiar contraseña" icono="vigente" descripcion="Usa una contraseña larga que no uses en otros sitios.">
                    <form method="POST" action="{{ route('password.update') }}" class="space-y-5"
                        x-data="fuerzaContrasena()">
                        @csrf
                        @method('PUT')
                        <div class="max-w-md">
                            <label for="current_password" class="etiqueta">Contraseña actual</label>
                            <input id="current_password" name="current_password" :type="ver ? 'text' : 'password'" type="password" autocomplete="current-password" class="campo h-11">
                            @if ($errors->updatePassword->first('current_password'))<p class="mt-1.5 text-sm font-medium text-error-600">{{ $errors->updatePassword->first('current_password') }}</p>@endif
                        </div>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="password" class="etiqueta">Contraseña nueva</label>
                                <input id="password" name="password" :type="ver ? 'text' : 'password'" type="password" autocomplete="new-password" minlength="8" x-model="nueva" class="campo h-11">
                                @if ($errors->updatePassword->first('password'))<p class="mt-1.5 text-sm font-medium text-error-600">{{ $errors->updatePassword->first('password') }}</p>@endif
                            </div>
                            <div>
                                <label for="password_confirmation" class="etiqueta">Confirmar contraseña nueva</label>
                                <input id="password_confirmation" name="password_confirmation" :type="ver ? 'text' : 'password'" type="password" autocomplete="new-password" class="campo h-11">
                            </div>
                        </div>

                        {{-- Medidor de seguridad --}}
                        <div x-show="nueva.length" x-cloak x-transition.opacity class="rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="grid flex-1 grid-cols-4 gap-1.5" aria-hidden="true">
                                    <template x-for="i in 4"><span class="h-1.5 rounded-full transition-colors duration-300" :class="i <= fuerza ? (fuerza <= 1 ? 'bg-rose-500' : fuerza === 2 ? 'bg-amber-400' : fuerza === 3 ? 'bg-real-500' : 'bg-emerald-500') : 'bg-gray-200'"></span></template>
                                </div>
                                <span class="w-20 text-right text-xs font-semibold" aria-live="polite"
                                    :class="fuerza <= 1 ? 'text-rose-600' : fuerza === 2 ? 'text-amber-700' : fuerza === 3 ? 'text-real-700' : 'text-emerald-700'"
                                    x-text="['Muy débil', 'Débil', 'Regular', 'Buena', 'Excelente'][fuerza]"></span>
                            </div>
                            <ul class="mt-3 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                <template x-for="r in reglas" :key="r.t">
                                    <li class="flex items-center gap-2 text-xs" :class="r.ok ? 'text-emerald-700' : 'text-gray-500'">
                                        <span class="flex size-4 items-center justify-center rounded-full" :class="r.ok ? 'bg-emerald-500 text-white' : 'bg-gray-200'">
                                            <svg x-show="r.ok" class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                                        </span>
                                        <span x-text="r.t"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 select-none">
                                <input type="checkbox" x-model="ver" class="size-4 rounded border-gray-300 text-udg-800"> Mostrar contraseñas
                            </label>
                            <button class="boton boton-primario w-full sm:w-auto">Actualizar contraseña</button>
                        </div>
                    </form>
                </x-dashboard.panel>
            </section>

            {{-- ===================== AVISOS Y PRIVACIDAD ===================== --}}
            <section id="panel-avisos" role="tabpanel" aria-labelledby="tab-avisos" x-show="tab === 'avisos'" x-cloak class="space-y-6 animate-entrada motion-reduce:animate-none">
                @php
                    $avisos = match ($rol) {
                        Rol::Alumno => [
                            ['Campana', 'Cada validación o rechazo de tu constancia IMSS, kardex, cartas y reportes, y los avances de tu postulación.'],
                            ['Correo', 'Cuando algún documento es rechazado, para que lo corrijas pronto.'],
                        ],
                        Rol::Empresa => [
                            ['Campana', 'Cada cambio de etapa de su convenio, observaciones de su expediente y la revisión de sus vacantes.'],
                        ],
                        Rol::Coordinador => [
                            ['Campana', 'Postulaciones nuevas, documentos por validar, reportes, vacantes por aprobar y convenios por dictaminar de tu carrera.'],
                        ],
                        Rol::Vinculacion, Rol::Administrador => [
                            ['Campana', 'Nuevas solicitudes de convenio, dictámenes recibidos y avances del trámite.'],
                            ['Correo', 'Cada nueva solicitud de convenio, con copia al buzón de Prácticas Profesionales.'],
                        ],
                        default => [['Campana', 'Los avisos de tus trámites.']],
                    };
                    $contactoDatos = isset($alumno)
                        ? 'tu coordinación (' . ($alumno->carrera->correoContacto() ?? config('practicas.contacto.correo')) . ')'
                        : 'Vinculación (' . config('practicas.contacto.correo') . ')';
                @endphp
                <x-dashboard.panel titulo="Avisos que recibes" icono="alerta" descripcion="Así te mantenemos al tanto de tus trámites." :enlace="route('notificaciones.index')" enlace-texto="Mis notificaciones">
                    <ul class="space-y-3">
                        @foreach ($avisos as [$canal, $texto])
                            <li class="flex items-start gap-3 rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-100">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $canal === 'Correo' ? 'bg-oro-50 text-oro-700 ring-oro-100' : 'bg-udg-50 text-udg-900 ring-udg-100' }} ring-1 ring-inset">
                                    @if ($canal === 'Correo')
                                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                    @else
                                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
                                    @endif
                                </span>
                                <span><span class="block text-sm font-semibold text-gray-900">{{ $canal }}</span><span class="text-sm text-gray-600">{{ $texto }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </x-dashboard.panel>

                {{-- Vinculación y el Administrador son el propio contacto para estos casos --}}
                @unless (in_array($rol, [Rol::Vinculacion, Rol::Administrador], true))
                    <x-dashboard.panel titulo="Tus datos personales" icono="vigente" descripcion="Tratamos tus datos conforme al aviso de privacidad del CUCSH.">
                        <p class="text-sm leading-relaxed text-gray-600">
                            ¿Quieres corregir datos que no puedes editar aquí o cancelar tu cuenta? Escribe a {{ $contactoDatos }}.
                        </p>
                        <a href="{{ route('privacidad') }}" class="enlace mt-3 inline-flex text-sm">Leer el aviso de privacidad</a>
                    </x-dashboard.panel>
                @endunless
            </section>
        </div>
    </div>
</div>
@endsection
