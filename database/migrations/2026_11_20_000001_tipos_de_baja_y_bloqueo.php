<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bajas de práctica según los criterios de la Coordinación de Carrera (CUCSH):
 *  - sin_reportes       → las horas se pierden (no hay reportes validados)
 *  - parcial_acreditada → baja justificada con reportes validados: se conservan las horas
 *  - sancion            → despido, abandono o falta grave: 0 horas y bloqueo hasta que la coordinación lo levante
 * Además, al acumular 2 bajas el alumno queda bloqueado; solo un desbloqueo excepcional lo libera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $table) {
            $table->string('tipo_baja', 30)->nullable()->after('motivo_baja');
            $table->timestamp('fecha_baja')->nullable()->after('tipo_baja');
        });

        Schema::table('alumnos', function (Blueprint $table) {
            $table->timestamp('desbloqueado_at')->nullable();
            $table->text('desbloqueo_justificacion')->nullable();
            $table->foreignId('desbloqueado_por')->nullable()->constrained('users')->nullOnDelete();
        });

        // Bajas existentes: se clasifican según sus horas validadas y lo que decidió la coordinación
        DB::table('practicas')->where('estado', 'cancelada')->orderBy('id')->each(function ($p) {
            $horas = (int) DB::table('reportes')->where('practica_id', $p->id)->where('estado', 'validado')->sum('horas_reportadas')
                + (int) ($p->horas_reconocidas ?? 0);
            $sancion = in_array($p->motivo_baja, ['despido', 'abandono', 'falta_grave'], true);
            $tipo = match (true) {
                $sancion => 'sancion',
                $p->conserva_horas && $horas > 0 => 'parcial_acreditada',
                default => 'sin_reportes',
            };

            DB::table('practicas')->where('id', $p->id)->update([
                'tipo_baja' => $tipo,
                'conserva_horas' => $tipo === 'parcial_acreditada',
                'fecha_baja' => $p->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desbloqueado_por');
            $table->dropColumn(['desbloqueado_at', 'desbloqueo_justificacion']);
        });

        Schema::table('practicas', function (Blueprint $table) {
            $table->dropColumn(['tipo_baja', 'fecha_baja']);
        });
    }
};
