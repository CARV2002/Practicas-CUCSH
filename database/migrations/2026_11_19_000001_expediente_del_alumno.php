<?php

use App\Enums\PostulacionEstado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nueva regla: el kardex y la constancia IMSS forman el EXPEDIENTE del alumno. Se entregan juntos a la
 * coordinación y deben estar validados ANTES de postularse.
 *
 *  - alumnos.porcentaje_creditos: lo declara el alumno al enviar su expediente (lo comprueba con el kardex).
 *  - Los kardex que vivían en cada postulación pasan al alumno.
 *  - Datos antiguos: un alumno que ya avanzó (carta emitida, práctica…) cumplió el requisito, así que
 *    su kardex e IMSS quedan validados; no puede existir un practicante con kardex «por revisar».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->decimal('porcentaje_creditos', 5, 2)->nullable()->after('turno');
        });

        $postulacion = 'App\\Models\\Postulacion';
        $alumno = 'App\\Models\\Alumno';

        // 1. Los kardex pasan de la postulación al alumno
        $kardex = DB::table('media')->where('model_type', $postulacion)->where('collection_name', 'kardex')->get();
        foreach ($kardex as $m) {
            $alumnoId = DB::table('postulaciones')->where('id', $m->model_id)->value('alumno_id');
            if ($alumnoId) {
                DB::table('media')->where('id', $m->id)->update(['model_type' => $alumno, 'model_id' => $alumnoId]);
            }
        }

        // 2. Créditos declarados: los de su postulación más reciente
        foreach (DB::table('postulaciones')->orderBy('id')->get(['alumno_id', 'porcentaje_creditos_declarado']) as $p) {
            DB::table('alumnos')->where('id', $p->alumno_id)->update(['porcentaje_creditos' => $p->porcentaje_creditos_declarado]);
        }

        // 3. Quien ya avanzó más allá de «pendiente» tiene su expediente validado
        $avanzados = DB::table('postulaciones')
            ->whereNotIn('estado', [PostulacionEstado::Pendiente->value, PostulacionEstado::Rechazada->value])
            ->pluck('alumno_id')->unique();

        foreach ($avanzados as $alumnoId) {
            foreach (['kardex', 'constancia_imss'] as $coleccion) {
                $vigente = DB::table('media')->where('model_type', $alumno)->where('model_id', $alumnoId)
                    ->where('collection_name', $coleccion)->orderByDesc('id')->first();
                if ($vigente) {
                    $props = json_decode($vigente->custom_properties ?: '[]', true) ?: [];
                    if (($props['estado'] ?? null) !== 'validada') {
                        $props['estado'] = 'validada';
                        $props['revisado_en'] = now()->toDateTimeString();
                        unset($props['motivo']);
                        DB::table('media')->where('id', $vigente->id)->update(['custom_properties' => json_encode($props)]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('alumnos', fn (Blueprint $t) => $t->dropColumn('porcentaje_creditos'));
    }
};
