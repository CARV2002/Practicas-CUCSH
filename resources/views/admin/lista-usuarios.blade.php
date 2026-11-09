{{-- resources/views/admin/lista-usuarios.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
@endphp

<x-common.page-breadcrumb pageTitle="Usuarios" />

<div x-data="{
    modalAlta: false,
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
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Usuarios del sistema</h3>
                <p class="mt-0.5 text-sm text-gray-500">Alumnos y empresas se registran solos; los administradores se dan de alta aquí.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o correo..." class="{{ $filtro }} w-48">
                    <select name="rol" class="{{ $filtro }}">
                        <option value="">Todos los roles</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(request('rol') === $r->value)>{{ ucfirst($r->value) }}</option>
                        @endforeach
                    </select>
                    <select name="estado" class="{{ $filtro }}">
                        <option value="">Cualquier estado</option>
                        <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                        <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                    </select>
                    <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
                </form>
                <button type="button" @click="modalAlta = true" class="bg-brand-500 hover:bg-brand-600 h-10 rounded-lg px-4 text-sm font-medium text-white">+ Nuevo administrador</button>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Usuario</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Rol</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Correo verificado</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Cuenta</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($usuarios as $u)
                        @php
                            $rol = $u->roles->first()?->name;
                            $colorRol = match ($rol) {
                                'administrador' => 'bg-purple-50 text-purple-700',
                                'empresa' => 'bg-blue-50 text-blue-700',
                                'alumno' => 'bg-green-50 text-green-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                            $esYo = $u->id === auth()->id();
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $u->name }}{{ $esYo ? ' (tú)' : '' }}</span>
                                <span class="text-xs text-gray-500">{{ $u->email }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorRol }}">{{ $rol ? ucfirst($rol) : 'Sin rol' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-600">
                                {{ $u->email_verified_at ? $u->email_verified_at->format('d/m/Y') : 'Pendiente' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $u->activo ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $u->activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" @click="cambiar({{ $u->id }}, @js($u->name))" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Contraseña</button>
                                    @unless ($esYo)
                                        <form method="POST" action="{{ route('admin.usuarios.activo', $u) }}"
                                            onsubmit="return confirm('{{ $u->activo ? '¿Desactivar' : '¿Activar' }} esta cuenta?')">
                                            @csrf
                                            <button class="text-xs font-semibold {{ $u->activo ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}">
                                                {{ $u->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay usuarios que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $usuarios->links() }}</div>
    </div>

    {{-- Modal: nuevo administrador --}}
    <div x-show="modalAlta" x-cloak @keydown.escape.window="modalAlta = false" class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalAlta = false"></div>
        <form method="POST" action="{{ route('admin.usuarios.store') }}" @click.stop class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <h3 class="text-lg font-semibold text-gray-800">Nuevo administrador</h3>
            <div><label class="{{ $label }}">Nombre</label><input type="text" name="name" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Correo</label><input type="email" name="email" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Puesto</label><input type="text" name="puesto" maxlength="255" placeholder="Coordinación de Prácticas Profesionales" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Contraseña</label><input type="password" name="password" required autocomplete="new-password" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Confirmar contraseña</label><input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalAlta = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Crear</button>
            </div>
        </form>
    </div>

    {{-- Modal: restablecer contraseña --}}
    <div x-show="modalPass" x-cloak @keydown.escape.window="modalPass = false" class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalPass = false"></div>
        <form method="POST" :action="actionPass" @click.stop class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <h3 class="text-lg font-semibold text-gray-800">Restablecer contraseña</h3>
            <p class="text-sm text-gray-500" x-text="pass.nombre"></p>
            <div><label class="{{ $label }}">Nueva contraseña</label><input type="password" name="password" required autocomplete="new-password" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Confirmar contraseña</label><input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalPass = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>[x-cloak]{display:none !important;}</style>
@endsection