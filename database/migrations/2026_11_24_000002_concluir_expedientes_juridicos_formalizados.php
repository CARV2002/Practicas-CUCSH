<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 1. Convenios ya formalizados: su expediente de Jurídica pasa a Archivado (Concluidos) para no saturar la bandeja activa.
 * 2. Correo institucional de la Unidad Jurídica precargado, para que el turno envíe copia desde el primer día.
 */
return new class extends Migration
{
    public function up(): void
    {
        $docs = DB::table('documentos_fisicos')
            ->join('convenios', 'convenios.id', '=', 'documentos_fisicos.convenio_id')
            ->where('convenios.estado', 'formalizado')
            ->where(fn ($q) => $q->where('documentos_fisicos.tipo', 'dictamen_juridico')
                ->orWhere(fn ($j) => $j->where('documentos_fisicos.tipo', 'juego_convenio')->where('documentos_fisicos.juego', 1)))
            ->whereNotIn('documentos_fisicos.estado', ['archivado', 'extraviado'])
            ->pluck('documentos_fisicos.id');

        foreach ($docs as $id) {
            DB::table('documentos_fisicos')->where('id', $id)->update([
                'estado' => 'archivado', 'fecha_estado' => now()->toDateString(), 'fecha_compromiso' => null, 'updated_at' => now(),
            ]);
            DB::table('documento_fisico_movimientos')->insert([
                'documento_fisico_id' => $id, 'estado' => 'archivado', 'fecha' => now()->toDateString(),
                'nota' => 'Convenio formalizado: el expediente jurídico concluye.', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (!DB::table('configuraciones')->where('clave', 'correo_juridica')->exists()) {
            DB::table('configuraciones')->insert(['clave' => 'correo_juridica', 'valor' => 'juridico@csh.udg.mx', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Los movimientos quedan en el historial
    }
};
