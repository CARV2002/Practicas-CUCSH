<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte híbrido: el alumno redacta en pantalla (número, periodo, horas, actividades y logros), el sistema
 * genera el formato estándar en PDF, el alumno recaba firmas y sube el escaneo. Si su carrera usa un formato
 * propio, sube ese (los datos capturados siguen llevando el conteo de horas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero')->nullable()->after('tipo');
            $table->text('actividades')->nullable()->after('horas_reportadas');
            $table->text('logros')->nullable()->after('actividades');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['numero', 'actividades', 'logros']);
        });
    }
};
