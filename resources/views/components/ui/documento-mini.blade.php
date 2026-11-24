@props([
    'href',              // ruta del archivo (…/archivos/{id})
    'nombre',            // cómo se llama el documento: «Kardex», «Aceptación»…
    'alerta' => false,   // en rojo cuando el documento ya no sirve (p. ej. IMSS vencido)
])

{{--
    Documento en espacio reducido (celdas de tabla): botón partido con la misma convención del sistema,
    descargar a la izquierda y ver a la derecha. El enlace «ver» abre la vista previa (resources/js/vista-previa.js);
    data-sin-boton-ver evita que ese script le agregue su propio botón «Ver documento».
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-stretch overflow-hidden rounded-lg bg-white text-xs shadow-theme-xs ring-1 ring-inset ' . ($alerta ? 'ring-rose-200' : 'ring-gray-200')]) }}>
    <a href="{{ $href }}" data-sin-vista data-sin-boton-ver title="Descargar {{ $nombre }}" aria-label="Descargar {{ $nombre }}"
        class="grid w-7 place-items-center border-r border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-udg-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-real-500/40">
        <x-dashboard.icono nombre="descargar" clase="size-3.5" />
    </a>
    <a href="{{ $href }}" data-sin-boton-ver data-nombre="{{ $nombre }}" title="Ver {{ $nombre }}"
        @class([
            'inline-flex items-center gap-1 px-2 py-1 font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-real-500/40',
            'text-udg-800 hover:bg-udg-50' => ! $alerta,
            'text-rose-700 hover:bg-rose-50' => $alerta,
        ])>
        <x-dashboard.icono nombre="ver" clase="size-3.5" />
        {{ $nombre }}
    </a>
</span>
