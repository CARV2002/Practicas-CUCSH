@props([
    'empresa',
    'titulo',
    'imagen',
    'fecha',
    'vacantes' => 0,
    'postulados' => 0,
    'url' => '#',
])

<div {{ $attributes->merge(['class' => 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl']) }}>

    <!-- Imagen -->
    <img
        src="{{ $imagen }}"
        alt="{{ $titulo }}"
        class="object-cover w-full h-48"
    >

    <!-- Contenido -->
    <div class="p-5">

        <!-- Empresa -->
        <p class="mb-2 text-sm font-medium text-gray-500">
            {{ $empresa }}
        </p>

        <!-- Badge -->
        <span class="inline-flex px-3 py-1 mb-3 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
            Con cupos
        </span>

        <!-- Título -->
        <h3 class="mb-3 text-lg font-semibold text-gray-900">
            {{ $titulo }}
        </h3>

        <!-- Fecha -->
        <div class="flex items-center mb-3 text-sm text-gray-500">
            📅 {{ $fecha }}
        </div>

        <!-- Estadísticas -->
        <div class="flex gap-6 mb-5 text-sm text-gray-600">
            <div>
                <strong>{{ $vacantes }}</strong><br>
                Vacantes
            </div>

            <div>
                <strong>{{ $postulados }}</strong><br>
                Postulados
            </div>
        </div>
        
        <!-- Botón -->
        <a
            href="{{ $url }}"
            class="block w-full px-4 py-3 font-medium text-center text-white transition bg-blue-600 rounded-xl hover:bg-blue-700"
        >
            Ver más
        </a>

    </div>

</div>