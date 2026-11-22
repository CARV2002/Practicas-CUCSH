<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procedimiento de la empresa (documento «PROCESO EMPRESA»): la cuenta se crea con lo mínimo y la solicitud
 * de convenio se llena por secciones, con borrador. Se agregan:
 *  - giro y domicilio fiscal desglosado (calle, números, colonia, C.P., municipio y estado);
 *  - de dónde salen las facultades del representante (acta o poder notarial) y los datos del poder;
 *  - el borrador de la solicitud (carreras, objetivos, actividades…) mientras la empresa la termina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('giro', 120)->nullable()->after('tipo_organismo');
            $table->string('calle', 120)->nullable()->after('domicilio_fiscal');
            $table->string('numero_exterior', 15)->nullable()->after('calle');
            $table->string('numero_interior', 15)->nullable()->after('numero_exterior');
            $table->string('colonia', 120)->nullable()->after('numero_interior');
            $table->string('codigo_postal', 5)->nullable()->after('colonia');
            $table->string('municipio', 120)->nullable()->after('codigo_postal');
            $table->string('entidad', 60)->nullable()->after('municipio');

            $table->string('facultad_origen', 15)->nullable()->after('facultad_firmante');   // acta | poder | nombramiento
            $table->string('poder_escritura_numero', 10)->nullable()->after('facultad_origen');
            $table->date('poder_fecha')->nullable()->after('poder_escritura_numero');
            $table->string('poder_notario_nombre')->nullable()->after('poder_fecha');
            $table->string('poder_notaria_numero', 4)->nullable()->after('poder_notario_nombre');
            $table->string('poder_notaria_ciudad', 120)->nullable()->after('poder_notaria_numero');

            $table->json('solicitud_borrador')->nullable()->after('proyectos_principales');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['giro', 'calle', 'numero_exterior', 'numero_interior', 'colonia', 'codigo_postal', 'municipio', 'entidad',
                'facultad_origen', 'poder_escritura_numero', 'poder_fecha', 'poder_notario_nombre', 'poder_notaria_numero', 'poder_notaria_ciudad',
                'solicitud_borrador']);
        });
    }
};
