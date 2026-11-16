@extends('layouts.landing')

@section('title', 'Aviso de privacidad | Prácticas Profesionales CUCSH')

@section('content')
  @include('pages.home.partials.legal-estilos')

  <div class="hero">
    <h1>Aviso de privacidad</h1>
    <p>Sistema de Prácticas Profesionales del CUCSH</p>
  </div>

  <main class="legal-wrap">
    <p class="legal-meta">Última actualización: 15 de noviembre de 2026</p>

    <h2>Responsable</h2>
    <p>La Universidad de Guadalajara, a través de la Coordinación de Prácticas Profesionales del Centro Universitario de
      Ciencias Sociales y Humanidades, es responsable del tratamiento de tus datos personales. Domicilio:
      {{ config('practicas.domicilio_cucsh') }}.
    </p>

    <h2>Datos que recabamos</h2>
    <ul>
      <li><strong>Alumnos:</strong> nombre, correo, código de alumno y carrera; kardex y porcentaje de créditos;
        constancia de seguro facultativo (IMSS) y su vigencia; cartas de aceptación y de término; reportes y horas.</li>
      <li><strong>Organizaciones:</strong> razón social, RFC y tipo de organismo; nombre, cargo, teléfono y correo de la
        persona de contacto; documentos del expediente (currículum de la instancia, identificación del representante, acta
        constitutiva o nombramiento legal, constancia de situación fiscal, comprobante de domicilio y, en su caso, poder
        notarial).</li>
      <li><strong>Uso de la plataforma y bitácora de actividad:</strong> datos de sesión y, por seguridad y trazabilidad,
        un registro de las acciones realizadas (quién, cuándo y qué cambió), la dirección IP y el navegador desde los que
        se accede, los intentos de inicio de sesión, incluidos los fallidos, y las descargas de documentos que realiza el
        personal.</li>
    </ul>

    <h2>Para qué los usamos</h2>
    <ul>
      <li>Crear y administrar tu cuenta y tu acceso.</li>
      <li>Validar requisitos, emitir cartas y oficios y dar seguimiento a postulaciones, prácticas, reportes y
        acreditación.</li>
      <li>Gestionar solicitudes de convenio, su validación académica y jurídica y su formalización.</li>
      <li>Enviarte notificaciones sobre el estado de tus trámites.</li>
      <li>Generar estadísticas internas y cifras generales sin datos personales.</li>
      <li>Mantener una bitácora de auditoría que permita aclarar quién realizó cada acción y proteger tus documentos y los
        de la organización.</li>
    </ul>
    <p>La razón social y el tipo de organismo de las organizaciones con convenio vigente se muestran en el sitio público.
    </p>

    <h2>Con quién los compartimos</h2>
    <ul>
      <li>Con las áreas de la Universidad que intervienen en el trámite: Coordinación, Divisiones, Unidad Jurídica,
        Rectoría y Control Escolar.</li>
      <li>Con la organización receptora, solo los datos necesarios para valorar y dar seguimiento a las postulaciones y
        prácticas de sus programas.</li>
    </ul>
    <p>No vendemos ni cedemos tus datos para fines comerciales.</p>

    <h2>Cómo los protegemos</h2>
    <p>El acceso a la información se restringe por tipo de cuenta y las contraseñas se almacenan de forma cifrada. Te
      recomendamos no cargar documentos con datos que no se soliciten.</p>

    <h2>Bitácora de actividad</h2>
    <p>No se registran contraseñas ni el contenido de tus archivos. Solo la Coordinación con alcance global puede
      consultar la bitácora; no se puede modificar ni borrar desde la plataforma y se conserva el tiempo que establezca la
      normatividad de archivo aplicable.</p>

    <h2>Informes y descargas</h2>
    <p>La Coordinación puede descargar informes de prácticas, postulaciones, convenios y programas que pueden incluir
      datos personales de alumnos. Cada cuenta solo descarga lo que está dentro de su alcance, la descarga queda
      registrada y la información se usa únicamente con fines administrativos.</p>

    <h2>Tus derechos</h2>
    <p>Puedes solicitar el acceso, la rectificación, la cancelación o la oposición al tratamiento de tus datos escribiendo
      a <a href="mailto:{{ config('practicas.contacto.correo') }}">{{ config('practicas.contacto.correo') }}</a>, o ante
      la Unidad de Transparencia de la Universidad de Guadalajara.</p>

    <h2>Conservación</h2>
    <p>Conservamos tus datos durante el trámite y el tiempo que establezca la normatividad de archivo aplicable.</p>

    <h2>Cambios a este aviso</h2>
    <p>Si este aviso cambia, publicaremos la versión actualizada en esta página.</p>

    <div class="legal-nav">
      <a href="{{ route('terminos') }}">Términos y condiciones</a>
      <a href="{{ route('inicio') }}">Volver al inicio</a>
    </div>
  </main>
@endsection