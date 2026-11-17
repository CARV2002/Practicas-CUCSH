<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La vacante de la empresa nace "pendiente_aprobacion" y la Coordinación la publica o rechaza
        Schema::table('programas', function (Blueprint $table) {
            $table->string('estado', 25)->default('borrador')->change();
            $table->text('motivo_rechazo')->nullable()->after('estado');
            $table->foreignId('revisado_por')->nullable()->after('motivo_rechazo')->constrained('personal')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable()->after('revisado_por');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['motivo_rechazo', 'revisado_at']);
        });
    }
};
