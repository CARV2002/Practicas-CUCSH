{{-- resources/views/admin/lista-usuarios.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        $input = 'campo h-11';
        $filtro = 'campo-filtro';
        $label = 'etiqueta';
    @endphp

    <x-common.page-breadcrumb pageTitle="Usuarios" />

    <div x-data="{
            modalAlta: false,
            rolAlta: @js($rolesAlta[0]->value),
            alcance: 'carrera',
            modalPass: false,
            pass: { id: null, nombre: '' },
            urlPass: '{{ route('admin.usuarios.password', 0) }}',
            cambiar(id, nombre) { this.pass = { id, nombre }; this.modalPass = true; },
            get actionPass() { return this.urlPass.replace(/0$/, this.pass.id); }
        }" class="space-y-6">

        @if (session('status'))
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="tarjeta overflow-hidden pt-5">
            <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Usuarios del sistema</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Alumnos y empresas se registran solos; las coordinaciones de carrera se
                        dan de alta aquí.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o correo..."
                            class="{{ $filtro }} sm:w-48">
                        <select name="rol" class="{{ $filtro }}">
                            <option value="">Todos los roles</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->value }}" @selected(request('rol') === $r->value)>{{ $r->label() }}
                                </option>
                            @endforeach
                        </select>
                        <select name="estado" class="{{ $filtro }}">
                            <option value="">Cualquier estado</option>
                            <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                            <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                        </select>
                        <button
                            class="boton boton-secundario w-full sm:w-auto">Filtrar</button>
                    </form>
                    <button type="button" @click="modalAlta = true"
                        class="boton boton-primario w-full sm:w-auto">+ Nueva
                        cuenta de personal</button>
                </div>
            </div>

            <div class="tabla-scroll">
                <table class="tabla">
                    <thead class="border-y border-gray-100 bg-gray-50/70">
                        <tr>
                            <th class="tabla-th px-6">Usuario</th>
                            <th class="tabla-th">Rol</th>
                            <th class="tabla-th">Correo
                                verificado</th>
                            <th class="tabla-th">Cuenta</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($usuarios as $u)
                            @php
                                $rol = $u->roles->first()?->name;
                                $colorRol = match ($rol) {
                                    'administrador' => 'bg-purple-50 text-purple-700',
                                    'vinculacion' => 'bg-orange-50 text-orange-700',
                                    'coordinador' => 'bg-teal-50 text-teal-700',
                                    'empresa' => 'bg-blue-50 text-blue-700',
                                    'alumno' => 'bg-green-50 text-green-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                $esYo = $u->id === auth()->id();
                                // Vinculación no gestiona cuentas de Vinculación ni del administrador técnico
                                $gestionable = $esYo || auth()->user()->hasRole(\App\Enums\Rol::Administrador->value) || ! $u->hasAnyRole(\App\Enums\Rol::deVinculacion());
                            @endphp
                            <tr class="tabla-fila">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <x-ui.user-avatar :user="$u" size="h-9 w-9 text-xs" />
                                        <div>
                                            <span
                                                class="block text-sm font-semibold text-gray-900">{{ $u->name }}{{ $esYo ? ' (tú)' : '' }}</span>
                                            <span class="text-xs text-gray-500">{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorRol }}">{{ $rol ? (\App\Enums\Rol::tryFrom($rol)?->label() ?? ucfirst($rol)) : 'Sin rol' }}</span>
                                    @if ($u->personal && ($u->personal->carrera || $u->personal->division))
                                        <span class="mt-1 block text-xs text-gray-500">{{ $u->personal->carrera?->carrera_clave ?? $u->personal->division?->nombre }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-600">
                                    {{ $u->email_verified_at ? $u->email_verified_at->format('d/m/Y') : 'Pendiente' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $u->activo ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                        {{ $u->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($gestionable)
                                        <button type="button" @click="cambiar({{ $u->id }}, @js($u->name))"
                                            class="text-real-600 hover:text-real-700 text-xs font-semibold">Contraseña</button>
                                        @unless ($esYo)
                                            <form method="POST" action="{{ route('admin.usuarios.activo', $u) }}"
                                                onsubmit="return confirm('{{ $u->activo ? '¿Desactivar' : '¿Activar' }} esta cuenta?')">
                                                @csrf
                                                <button
                                                    class="text-xs font-semibold {{ $u->activo ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}">
                                                    {{ $u->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        @endunless
                                        @unless ($u->email_verified_at)
                                            <form method="POST" action="{{ route('admin.usuarios.verificar', $u) }}"
                                                onsubmit="return confirm('¿Marcar este correo como verificado?')">
                                                @csrf
                                                <button class="text-xs font-semibold text-gray-600 hover:text-gray-800">Verificar
                                                    correo</button>
                                            </form>
                                        @endunless
                                        @else
                                            <span class="text-xs text-gray-400">Solo administrador técnico</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay usuarios que
                                    coincidan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-6 py-4">{{ $usuarios->links() }}</div>
        </div>

        {{-- Modal: nuevo administrador --}}
        <div x-show="modalAlta" x-cloak @keydown.escape.window="modalAlta = false"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
            <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalAlta = false"></div>
            <form method="POST" action="{{ route('admin.usuarios.store') }}" @click.stop
                class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-theme-xl">
                @csrf
                <h3 class="text-lg font-semibold text-gray-800">Nueva cuenta de personal</h3>
                <div><label class="{{ $label }}">Nombre</label><input type="text" name="name" required maxlength="255"
                        class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Correo</label><input type="email" name="email" required maxlength="255"
                        class="{{ $input }}"></div>
                <div>
                    <label class="{{ $label }}">Rol</label>
                    <select name="rol" x-model="rolAlta" class="{{ $input }}">
                        @foreach ($rolesAlta as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <template x-if="rolAlta === 'coordinador'">
                    <div class="space-y-3">
                        <div class="flex gap-4 text-sm text-gray-700">
                            <label class="flex items-center gap-1.5"><input type="radio" value="carrera" x-model="alcance"> Coordina una carrera</label>
                            <label class="flex items-center gap-1.5"><input type="radio" value="division" x-model="alcance"> Toda una división</label>
                        </div>
                        <div x-show="alcance === 'carrera'">
                            <label class="{{ $label }}">Carrera</label>
                            <select name="carrera_id" :disabled="alcance !== 'carrera'" :required="alcance === 'carrera'" class="{{ $input }}">
                                <option value="">Selecciona...</option>
                                @foreach ($carreras as $c)
                                    <option value="{{ $c->id }}">{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="alcance === 'division'">
                            <label class="{{ $label }}">División</label>
                            <select name="division_id" :disabled="alcance !== 'division'" :required="alcance === 'division'" class="{{ $input }}">
                                <option value="">Selecciona...</option>
                                @foreach ($divisiones as $d)
                                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </template>
                <div><label class="{{ $label }}">Puesto</label><input type="text" name="puesto" maxlength="255"
                        placeholder="Coordinación de la Licenciatura en..." class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Contraseña</label><input type="password" name="password" required
                        autocomplete="new-password" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Confirmar contraseña</label><input type="password"
                        name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="modalAlta = false"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="bg-udg-800 hover:bg-udg-700 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Crear</button>
                </div>
            </form>
        </div>

        {{-- Modal: restablecer contraseña --}}
        <div x-show="modalPass" x-cloak @keydown.escape.window="modalPass = false"
            class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
            <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalPass = false"></div>
            <form method="POST" :action="actionPass" @click.stop
                class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-theme-xl">
                @csrf
                <h3 class="text-lg font-semibold text-gray-800">Restablecer contraseña</h3>
                <p class="text-sm text-gray-500" x-text="pass.nombre"></p>
                <div><label class="{{ $label }}">Nueva contraseña</label><input type="password" name="password" required
                        autocomplete="new-password" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Confirmar contraseña</label><input type="password"
                        name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="modalPass = false"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="bg-udg-800 hover:bg-udg-700 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection