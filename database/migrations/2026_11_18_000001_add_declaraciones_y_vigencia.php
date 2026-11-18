<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Declaraciones de la ENTIDAD del Formato de PP: las captura la empresa una sola vez
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('representante_cargo')->nullable()->after('representante_legal');
            $table->text('constitucion')->nullable()->after('representante_cargo');
            $table->text('objeto_social')->nullable()->after('constitucion');
            $table->text('facultad_firmante')->nullable()->after('objeto_social');
        });

        // Duración pactada (cláusula UNDÉCIMA); calcula la fecha de fin al formalizar
        Schema::table('convenios', function (Blueprint $table) {
            $table->unsignedTinyInteger('vigencia_anios')->nullable()->after('fecha_turno_division');
        });

        // Declaración del alumno: ya inscribió la materia de prácticas en SIIAU
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->timestamp('siiau_confirmado_at')->nullable();
        });

        // Baja o cambio de plaza: motivo y decisión sobre las horas
        Schema::table('practicas', function (Blueprint $table) {
            $table->string('motivo_baja', 30)->nullable();
            $table->text('detalle_baja')->nullable();
            $table->boolean('conserva_horas')->nullable();
            $table->unsignedSmallInteger('horas_reconocidas')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('practicas', fn (Blueprint $t) => $t->dropColumn(['motivo_baja', 'detalle_baja', 'conserva_horas', 'horas_reconocidas']));
        Schema::table('postulaciones', fn (Blueprint $t) => $t->dropColumn('siiau_confirmado_at'));
        Schema::table('convenios', fn (Blueprint $t) => $t->dropColumn('vigencia_anios'));
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn(['representante_cargo', 'constitucion', 'objeto_social', 'facultad_firmante']));
    }
};
