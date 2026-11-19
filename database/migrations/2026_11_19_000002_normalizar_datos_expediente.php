<?php

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Models\Alumno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lleva los datos existentes a las reglas vigentes:
 *  1. Una postulación pendiente exige expediente validado: las de alumnos sin expediente validado se cancelan
 *     (el alumno entrega su kardex e IMSS y, una vez validados, se postula de nuevo).
 *  2. Las bajas registradas antes del flujo formal no tenían decisión sobre las horas: se conservan
 *     (no se pierden horas ya validadas) y el alumno puede postularse otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pendientes = DB::table('postulaciones')->where('estado', PostulacionEstado::Pendiente->value)->get(['id', 'alumno_id']);

        foreach ($pendientes as $p) {
            $alumno = Alumno::find($p->alumno_id);
            if ($alumno && !$alumno->expedienteValidado()) {
                DB::table('postulaciones')->where('id', $p->id)->update([
                    'estado' => PostulacionEstado::Cancelada->value,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('practicas')
            ->where('estado', PracticaEstado::Cancelada->value)
            ->whereNull('conserva_horas')
            ->update([
                'motivo_baja' => 'otro',
                'detalle_baja' => 'Baja registrada antes del flujo formal de bajas; se conservan las horas validadas.',
                'conserva_horas' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Ajuste de datos: no se revierte
    }
};
