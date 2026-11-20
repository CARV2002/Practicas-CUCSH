<?php

use App\Models\Alumno;
use App\Models\Postulacion;
use Illuminate\Database\Migrations\Migration;

/**
 * Regla: nadie se postula sin su kardex e IMSS validados por su coordinación.
 *
 * Las postulaciones de alumnos que HOY no tienen su expediente validado y que nunca pasaron de
 * «pendiente» (sin carta de presentación ni práctica) no debieron existir: se muestran como
 * «Rechazada» o «Cancelada» y confunden al alumno. Se eliminan junto con sus archivos.
 * El historial con carta emitida o con práctica (bajas, acreditaciones) se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Postulacion::with(['alumno', 'practica', 'media'])
            ->whereIn('estado', ['pendiente', 'rechazada', 'cancelada'])
            ->get()
            ->filter(fn (Postulacion $po) => $po->practica === null
                && $po->getMedia('carta_presentacion')->isEmpty()
                && $po->alumno instanceof Alumno
                && !$po->alumno->expedienteValidado())
            ->each(fn (Postulacion $po) => $po->delete());
    }

    public function down(): void
    {
        // Limpieza de datos: no se revierte
    }
};
