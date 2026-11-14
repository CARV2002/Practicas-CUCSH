@extends('layouts.landing')

@section('title', 'Términos y condiciones | Prácticas Profesionales CUCSH')

@section('content')
@include('pages.home.partials.legal-estilos')

<div class="hero">
  <h1>Términos y condiciones</h1>
  <p>Sistema de Prácticas Profesionales del CUCSH</p>
</div>

<main class="legal-wrap">
  <p class="legal-meta">Última actualización: 13 de noviembre de 2026</p>

  <h2>1. Objeto</h2>
  <p>Esta plataforma sirve para gestionar las prácticas profesionales y los convenios de vinculación del Centro Universitario de Ciencias Sociales y Humanidades (CUCSH) de la Universidad de Guadalajara. Al registrarte o iniciar sesión aceptas estos términos. La plataforma no sustituye la normatividad universitaria ni los convenios firmados.</p>

  <h2>2. Quién puede usar la plataforma</h2>
  <ul>
    <li><strong>Alumnos</strong> del CUCSH, con su código de alumno y su carrera.</li>
    <li><strong>Organizaciones</strong> interesadas en establecer un convenio, a través de su representante o persona de contacto.</li>
    <li><strong>Personal administrativo</strong>, cuyas cuentas crea la Coordinación.</li>
  </ul>

  <h2>3. Cuentas y seguridad</h2>
  <ul>
    <li>Debes proporcionar datos verdaderos y mantenerlos actualizados.</li>
    <li>Tus credenciales son personales. No las compartas y avisa a la Coordinación si sospechas un uso no autorizado.</li>
    <li>La Coordinación puede desactivar una cuenta; una cuenta desactivada no puede iniciar sesión.</li>
  </ul>

  <h2>4. Documentos y reportes</h2>
  <ul>
    <li>Eres responsable de que los documentos que cargas sean auténticos, legibles y estén en el formato solicitado (hasta 10 MB cada uno).</li>
    <li>Presentar documentos falsos o alterados puede cancelar el trámite y dar lugar a las medidas que prevea la normatividad universitaria.</li>
    <li>Un documento puede ser rechazado si no cumple los requisitos; en ese caso deberás corregirlo y cargarlo de nuevo.</li>
  </ul>

  <h2>5. Prácticas profesionales</h2>
  <ul>
    <li>La Coordinación valida cada postulación, emite la carta de presentación y activa la práctica.</li>
    <li>Solo puedes tener una empresa o práctica en trámite a la vez.</li>
    <li>Tus horas cuentan cuando se validan los reportes correspondientes.</li>
    <li>La práctica se acredita cuando la Coordinación valida tu carta de término y se envía el oficio a Control Escolar para su captura.</li>
  </ul>

  <h2>6. Convenios con organizaciones</h2>
  <ul>
    <li>Enviar una solicitud no garantiza que el convenio se formalice.</li>
    <li>El convenio pasa por revisión de documentos, validación académica por carrera, dictamen jurídico y firmas.</li>
    <li>Solo con un convenio vigente, y para las carreras aprobadas en él, se pueden publicar programas.</li>
    <li>Los plazos que se muestran son estimaciones y pueden variar.</li>
  </ul>

  <h2>7. Uso adecuado</h2>
  <p>Está prohibido suplantar a otra persona, cargar contenido ilícito o malicioso, intentar acceder a información de otros usuarios o interferir con el funcionamiento de la plataforma.</p>

  <h2>8. Propiedad intelectual</h2>
  <p>El nombre, los escudos y las marcas de la Universidad de Guadalajara y del CUCSH pertenecen a la institución. Los documentos que cargas siguen siendo tuyos; al cargarlos autorizas su uso para el trámite correspondiente.</p>

  <h2>9. Disponibilidad</h2>
  <p>Procuramos que la plataforma esté disponible, pero puede interrumpirse por mantenimiento o causas ajenas. Las condiciones específicas de cada práctica se rigen por el convenio y la normatividad universitaria aplicable.</p>

  <h2>10. Privacidad</h2>
  <p>El tratamiento de tus datos personales se describe en el <a href="{{ route('privacidad') }}">aviso de privacidad</a>.</p>

  <h2>11. Cambios</h2>
  <p>Podemos actualizar estos términos. La versión vigente es la publicada en esta página, con su fecha de actualización.</p>

  <h2>12. Contacto</h2>
  <p>Para dudas sobre estos términos escribe a <a href="mailto:{{ config('practicas.contacto.correo') }}">{{ config('practicas.contacto.correo') }}</a> o visita la <a href="{{ route('contacto') }}">página de contacto</a>.</p>

  <div class="legal-nav">
    <a href="{{ route('privacidad') }}">Aviso de privacidad</a>
    <a href="{{ route('inicio') }}">Volver al inicio</a>
  </div>
</main>
@endsection