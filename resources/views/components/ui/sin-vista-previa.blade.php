<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $media->file_name }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #1f2937; }
        .ficha { max-width: 26rem; padding: 2rem; text-align: center; }
        .icono { width: 3.5rem; height: 3.5rem; margin: 0 auto 1rem; border-radius: 1rem; display: grid; place-items: center; background: #eef2ff; color: #1b2a4a; font-weight: 700; }
        h1 { font-size: 1rem; margin: 0 0 .25rem; word-break: break-all; }
        p { font-size: .875rem; color: #6b7280; margin: 0 0 1.25rem; }
        a { display: inline-block; padding: .6rem 1rem; border-radius: .6rem; background: #1b2a4a; color: #fff; text-decoration: none; font-weight: 600; font-size: .875rem; }
    </style>
</head>
<body>
    <div class="ficha">
        <div class="icono">{{ strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION)) ?: 'DOC' }}</div>
        <h1>{{ $media->file_name }}</h1>
        <p>Este tipo de archivo no se puede previsualizar en el navegador ({{ number_format($media->size / 1048576, 1) }} MB). Descárgalo para abrirlo en Word.</p>
        <a href="{{ $descarga }}" target="_top">Descargar archivo</a>
    </div>
</body>
</html>
