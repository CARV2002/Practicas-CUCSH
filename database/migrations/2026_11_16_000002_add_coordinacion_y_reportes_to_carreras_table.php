<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            // Directorio: el alumno se dirige a su coordinación (no a Vinculación)
            $table->string('coordinacion_correo')->nullable()->after('creditos_otorgados');
            $table->string('coordinacion_telefono', 40)->nullable()->after('coordinacion_correo');
            // Periodicidad de reportes que define cada carrera: App\Enums\EsquemaReportes
            $table->string('esquema_reportes', 20)->default('mensual')->after('coordinacion_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('carreras', function (Blueprint $table) {
            $table->dropColumn(['coordinacion_correo', 'coordinacion_telefono', 'esquema_reportes']);
        });
    }
};
