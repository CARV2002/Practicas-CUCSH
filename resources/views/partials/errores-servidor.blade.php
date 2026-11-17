{{-- Errores de validación del servidor: validacion.js los coloca junto a su campo, en rojo --}}
<script>
    window.__erroresServidor = @json($errors->getMessages());
</script>
