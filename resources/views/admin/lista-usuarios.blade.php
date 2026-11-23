@extends('layouts.app')

@section('content')
@php
    use App\Enums\Rol;

    $familiaRol = ['administrador' => 'indigo', 'vinculacion' => 'ambar', 'coordinador' => 'turquesa', 'empresa' => 'cielo', 'alumno' => 'menta'];
    $iconoRol = ['administrador' => 'candado', 'vinculacion' => 'firma', 'coordinador' => 'academico', 'empresa' => 'empresa', 'alumno' => 'alumno'];
    $opciones = collect($roles)->map(fn ($r) => ['valor' => $r->value, 'texto' => $r->label(), 'conteo' => (int) ($conteosRol[$r->value] ?? 0), 'familia' => $familiaRol[$r->value] ?? 'gris'])
        ->prepend(['valor' => '', 'texto' => 'Todos', 'conteo' => $totalUsuarios])->all();
    $filtrando = request()->hasAny(['q', 'rol', 'estado']);
    $personal = ($conteosRol['administrador'] ?? 0) + ($conteosRol['vinculacion'] ?? 0) + ($conteosRol['coordinador'] ?? 0);
@endphp

<div x-data="{
        modalAlta: {{ $errors->hasAny(['name', 'email', 'rol', 'carrera_id', 'division_id']) ? 'true' : 'false' }},
        rolAlta: @js(old('rol', $rolesAlta[0]->value)),
        alcance: @js(old('division_id') ? 'division' : 'carrera'),
        {{-- Cancelar o cerrar el alta borra lo capturado y los avisos de validación, para empezar limpio --}}
        cerrarAlta() {
            const f = this.$refs.formAlta;
            f.querySelectorAll('input[type=text], input[type=email]').forEach(i => { i.value = ''; i.removeAttribute('aria-invalid'); });
            f.querySelectorAll('select').forEach(s => { s.value = ''; });
            f.querySelectorAll('.js-mensaje-campo, .js-aviso-formulario').forEach(p => p.remove());
            this.rolAlta = @js($rolesAlta[0]->value);
            this.alcance = 'carrera';
            this.modalAlta = false;
        },
        modalPass: false,
        pass: { id: null, nombre: '' },
        urlPass: '{{ route('admin.usuarios.password', 0) }}',
        cambiar(id, nombre) { this.pass = { id, nombre }; this.modalPass = true; },
        get actionPass() { return this.urlPass.replace(/0$/, this.pass.id); }
    }" class="space-y-6">

    <x-pagina.encabezado titulo="Usuarios y coordinadores" descripcion="Alumnos y empresas se registran solos; aquí das de alta al personal (Vinculación y coordinaciones de carrera) y cuidas el acceso de todas las cuentas.">
        <x-slot:acciones>
            <button type="button" x-on:click="modalAlta = true" class="boton boton-primario"><x-dashboard.icono nombre="mas" clase="size-4" /> Nueva cuenta de personal</button>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-dashboard.kpi :href="route('admin.lista-usuarios')" etiqueta="Cuentas" :valor="$totalUsuarios" icono="usuarios" tono="udg" :detalle="$personal . ' de personal institucional'" :orden="0" />
        <x-dashboard.kpi :href="route('admin.lista-usuarios', ['rol' => 'coordinador'])" etiqueta="Coordinaciones" :valor="$conteosRol['coordinador'] ?? 0" icono="academico" tono="turquesa" detalle="Con carrera o división asignada" :orden="1" />
        <x-dashboard.kpi :href="route('admin.lista-usuarios', ['estado' => 'inactivo'])" etiqueta="Inactivas" :valor="$inactivos" icono="apagar" tono="gris" detalle="No pueden iniciar sesión" :orden="2" />
        <x-dashboard.kpi etiqueta="Correo sin verificar" :valor="$sinVerificar" icono="alerta" tono="ambar" detalle="Puedes verificarlo a mano" :orden="3" />
    </div>

    <x-tabla.marco titulo="Cuentas del sistema" :total="$usuarios->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado :opciones="$opciones" parametro="rol" :actual="request('rol', '')" etiqueta="Filtrar por rol" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center" role="search">
                @if (request('rol'))<input type="hidden" name="rol" value="{{ request('rol') }}">@endif
                <div class="relative flex-1 sm:max-w-sm">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por nombre o correo</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o correo" class="campo-filtro w-full pl-9">
                </div>
                <label for="estado" class="sr-only">Estado de la cuenta</label>
                <select id="estado" name="estado" class="campo-filtro" onchange="this.form.requestSubmit()">
                    <option value="">Activas e inactivas</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Solo activas</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Solo inactivas</option>
                </select>
                <button class="boton boton-secundario">Buscar</button>
                @if ($filtrando)
                    <a href="{{ route('admin.lista-usuarios') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Usuario</th>
                    <th class="tabla-th">Rol y alcance</th>
                    <th class="tabla-th">Correo</th>
                    <th class="tabla-th">Cuenta</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($usuarios as $u)
                    @php
                        $rol = $u->roles->first()?->name;
                        $esYo = $u->id === auth()->id();
                        // Vinculación no gestiona cuentas de Vinculación ni del administrador
                        $gestionable = $esYo || auth()->user()->hasRole(Rol::Administrador->value) || ! $u->hasAnyRole(Rol::deVinculacion());
                        $alcance = $u->personal?->carrera ? $u->personal->carrera->carrera_clave . ' · ' . $u->personal->carrera->nombre : $u->personal?->division?->nombre;
                    @endphp
                    <tr @class(['tabla-fila', 'opacity-70' => !$u->activo])>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <x-ui.user-avatar :user="$u" size="size-10 shrink-0 text-xs ring-2 ring-white" />
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-900">
                                        {{ $u->name }}
                                        @if ($esYo)<span class="rounded-full bg-udg-50 px-1.5 py-0.5 text-[10px] font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">Tú</span>@endif
                                    </p>
                                    <p class="truncate text-xs text-gray-500">{{ $u->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="md:min-w-44">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ \App\Support\Estatus::chip($familiaRol[$rol] ?? 'gris') }}">
                                    <x-dashboard.icono :nombre="$iconoRol[$rol] ?? 'usuarios'" clase="size-3.5" />
                                    {{ $rol ? (Rol::tryFrom($rol)?->label() ?? ucfirst($rol)) : 'Sin rol' }}
                                </span>
                                @if ($rol === 'coordinador')
                                    <p class="mt-1 text-xs {{ $alcance ? 'text-gray-600' : 'font-medium text-rose-600' }}">{{ $alcance ?: 'Sin carrera ni división asignada' }}</p>
                                @elseif ($u->personal?->puesto)
                                    <p class="mt-1 text-xs text-gray-500">{{ $u->personal->puesto }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            @if ($u->email_verified_at)
                                <p class="flex items-center gap-1 text-xs font-medium text-emerald-700"><x-dashboard.icono nombre="check" clase="size-3.5" /> Verificado</p>
                                <p class="text-xs text-gray-500">{{ $u->email_verified_at->format('d/m/Y') }}</p>
                            @else
                                <p class="flex items-center gap-1 text-xs font-medium text-amber-700"><x-dashboard.icono nombre="reloj" clase="size-3.5" /> Sin verificar</p>
                                <p class="text-xs text-gray-500">Desde el {{ $u->created_at?->format('d/m/Y') ?? 'registro' }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <x-ui.estatus :familia="$u->activo ? 'menta' : 'piedra'" :texto="$u->activo ? 'Activa' : 'Inactiva'" />
                        </td>
                        <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($gestionable)
                                    @unless ($u->email_verified_at)
                                        <x-ui.boton-icono :accion="route('admin.usuarios.verificar', $u)" icono="check" etiqueta="Verificar correo" tono="exito" confirmar="¿Marcar este correo como verificado?" />
                                    @endunless
                                    <button type="button" x-on:click="cambiar({{ $u->id }}, @js($u->name))"
                                        class="group/btn relative inline-flex size-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-theme-xs transition hover:border-udg-200 hover:bg-udg-50 hover:text-udg-900 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25"
                                        aria-label="Enviar enlace de contraseña">
                                        <x-dashboard.icono nombre="candado" clase="size-4" />
                                        <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-udg-950 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-theme-md transition group-hover/btn:opacity-100" aria-hidden="true">Enviar enlace de contraseña</span>
                                    </button>
                                    @unless ($esYo)
                                        <x-ui.boton-icono :accion="route('admin.usuarios.activo', $u)" :icono="$u->activo ? 'apagar' : 'encender'"
                                            :etiqueta="$u->activo ? 'Desactivar cuenta' : 'Activar cuenta'" :tono="$u->activo ? 'peligro' : 'exito'"
                                            :confirmar="($u->activo ? '¿Desactivar' : '¿Activar') . ' la cuenta de ' . $u->name . '?'" />
                                    @endunless
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400"><x-dashboard.icono nombre="candado" clase="size-3.5" /> Solo administrador</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="usuarios" :titulo="$filtrando ? 'No hay cuentas con ese filtro' : 'Aún no hay cuentas'" :texto="$filtrando ? 'Prueba con otro nombre, correo o rol.' : 'Crea la primera cuenta de personal con el botón de arriba.'" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $usuarios->links() }}</x-slot:pie>
    </x-tabla.marco>

    {{-- Modal: nueva cuenta de personal --}}
    <div x-show="modalAlta" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="cerrarAlta()"
        class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-alta">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="cerrarAlta()"></div>
        <form method="POST" action="{{ route('admin.usuarios.store') }}" x-ref="formAlta" x-on:click.stop
            class="relative w-full max-w-lg animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
            @csrf
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h3 id="titulo-alta" class="font-display text-lg font-bold text-gray-900">Nueva cuenta de personal</h3>
                    <p class="text-xs text-gray-500">La persona entra con este correo y la contraseña que ella misma configure.</p>
                </div>
                <button type="button" x-on:click="cerrarAlta()" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Cerrar"><x-dashboard.icono nombre="equis" clase="size-4" /></button>
            </div>
            <div class="space-y-4 px-6 py-5">
                <fieldset>
                    <legend class="etiqueta">Rol</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-{{ min(3, count($rolesAlta)) }}">
                        @foreach ($rolesAlta as $r)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 px-3 py-2.5 text-sm transition hover:border-udg-200 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-50 has-[:checked]:text-udg-900">
                                <input type="radio" name="rol" value="{{ $r->value }}" x-model="rolAlta" class="sr-only">
                                <x-dashboard.icono :nombre="$iconoRol[$r->value] ?? 'usuarios'" clase="size-4 shrink-0" />
                                <span class="font-medium">{{ $r->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="etiqueta" for="alta-nombre">Nombre completo <span class="text-rose-500">*</span></label><input id="alta-nombre" type="text" data-solo="letras" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+(\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ&#039;.\-]+)+" data-msg-formato="Escribe nombre y apellido, solo con letras." name="name" value="{{ old('name') }}" required minlength="5" maxlength="80" data-solo="letras" pattern="{{ \App\Support\Formatos::NOMBRE_PERSONA_HTML }}" data-msg-formato="Escribe nombre y apellido, solo con letras." placeholder="Ej. Mtra. Laura Gómez Ruiz" class="campo h-11"></div>
                    <div><label class="etiqueta" for="alta-correo">Correo institucional <span class="text-rose-500">*</span></label><input id="alta-correo" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="Ej. laura.gomez@academicos.udg.mx" pattern=".+@({{ collect(config('practicas.google.dominios_personal'))->map(fn ($x) => preg_quote($x))->join('|') }})" data-msg-formato="Usa su correo institucional de la UdG." class="campo h-11"></div>
                </div>
                <template x-if="rolAlta === 'coordinador'">
                    <div class="space-y-3 rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-100">
                        <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                            <label class="flex items-center gap-1.5"><input type="radio" value="carrera" x-model="alcance"> Coordina una carrera</label>
                            <label class="flex items-center gap-1.5"><input type="radio" value="division" x-model="alcance"> Toda una división</label>
                        </div>
                        <div x-show="alcance === 'carrera'">
                            <label class="etiqueta" for="alta-carrera">Carrera</label>
                            <select id="alta-carrera" name="carrera_id" :disabled="alcance !== 'carrera'" :required="alcance === 'carrera'" class="campo h-11">
                                <option value="">Selecciona…</option>
                                @foreach ($carreras as $c)
                                    @php $n = $activosPorCarrera[$c->id] ?? 0; @endphp
                                    <option value="{{ $c->id }}" @selected(old('carrera_id') == $c->id) @disabled($n >= $limitePorCoordinacion)>{{ $c->carrera_clave }} · {{ $c->nombre }} ({{ $n }}/{{ $limitePorCoordinacion }} activos{{ $n >= $limitePorCoordinacion ? ' · completa' : '' }})</option>
                                @endforeach
                            </select>
                            @error('carrera_id')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="alcance === 'division'">
                            <label class="etiqueta" for="alta-division">División</label>
                            <select id="alta-division" name="division_id" :disabled="alcance !== 'division'" :required="alcance === 'division'" class="campo h-11">
                                <option value="">Selecciona…</option>
                                @foreach ($divisiones as $d)
                                    @php $n = $activosPorDivision[$d->id] ?? 0; @endphp
                                    <option value="{{ $d->id }}" @selected(old('division_id') == $d->id) @disabled($n >= $limitePorCoordinacion)>{{ $d->nombre }} ({{ $n }}/{{ $limitePorCoordinacion }} activos{{ $n >= $limitePorCoordinacion ? ' · completa' : '' }})</option>
                                @endforeach
                            </select>
                            @error('division_id')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                        </div>
                        <p class="text-xs text-gray-500">Hasta {{ $limitePorCoordinacion }} encargados activos por coordinación (titular y asistente o secretario administrativo). Para relevar a alguien, desactiva su cuenta: se conserva su historial y se libera el cupo.</p>
                    </div>
                </template>
                <div><label class="etiqueta" for="alta-puesto">Puesto <span class="font-normal text-gray-400">(opcional)</span></label><input id="alta-puesto" type="text" name="puesto" value="{{ old('puesto') }}" maxlength="255" placeholder="Coordinación de la Licenciatura en…" class="campo h-11"></div>
                <p class="flex items-start gap-2 rounded-xl bg-udg-50/60 px-4 py-3 text-sm text-udg-900 ring-1 ring-inset ring-udg-100">
                    <x-dashboard.icono nombre="candado" clase="mt-0.5 size-4 shrink-0" />
                    <span>No escribes contraseña: al crear la cuenta le enviamos a su correo institucional un enlace para que configure la suya (vence en 72 horas). Al usarlo, su cuenta se activa y su correo queda verificado.</span>
                </p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                <button type="button" x-on:click="cerrarAlta()" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario"><x-dashboard.icono nombre="check" clase="size-4" /> Crear cuenta</button>
            </div>
        </form>
    </div>

    {{-- Modal: restablecer contraseña --}}
    <div x-show="modalPass" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="modalPass = false"
        class="fixed inset-0 z-[100050] flex items-center justify-center overflow-y-auto p-5" role="dialog" aria-modal="true" aria-labelledby="titulo-pass">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="modalPass = false"></div>
        <form method="POST" :action="actionPass" x-on:click.stop
            class="relative w-full max-w-md animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
            @csrf
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 id="titulo-pass" class="font-display text-lg font-bold text-gray-900">Enviar enlace de contraseña</h3>
                <p class="text-sm text-gray-500">Para <span class="font-semibold text-gray-800" x-text="pass.nombre"></span>.</p>
            </div>
            <div class="px-6 py-5 text-sm leading-relaxed text-gray-600">
                Le enviaremos a su correo un enlace personal (vence en 72 horas) para que configure una contraseña nueva. Tú nunca conoces su contraseña.
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                <button type="button" x-on:click="modalPass = false" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario"><x-dashboard.icono nombre="enviar" clase="size-4" /> Enviar enlace</button>
            </div>
        </form>
    </div>
</div>
@endsection
