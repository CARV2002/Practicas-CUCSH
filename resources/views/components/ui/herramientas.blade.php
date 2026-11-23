{{--
    Barra de acciones secundarias de una fila (ver, editar, eliminar…): un marco con divisiones.
    Las esquinas de cada botón siguen al marco para que el fondo al pasar el mouse no se salga.
--}}
<div {{ $attributes->merge(['class' => 'inline-flex items-stretch divide-x divide-gray-200 rounded-lg border border-gray-200 bg-white shadow-theme-xs [&>*:first-child]:rounded-l-[7px] [&>*:last-child]:rounded-r-[7px] [&>*]:overflow-visible [&>*:first-child_button]:rounded-l-[7px] [&>*:last-child_button]:rounded-r-[7px] [&>button:first-child]:rounded-l-[7px] [&>button:last-child]:rounded-r-[7px]']) }}>
    {{ $slot }}
</div>
