<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Papeles que ya cumplieron su recorrido y seguían «En seguimiento»:
 * 1. Expediente de Jurídica en convenios con dictamen favorable (en firmas o formalizados) → Archivado.
 * 2. Juegos de convenios formalizados → reparto final: juego 1 Archivado (Vinculación); juegos 2 y 3 Entregados.
 * 3. El juego 3 va al archivo de la Unidad Jurídica, no a Rectoría.
 */
return new class extends Migration
{
    /** Estados que ya no requieren seguimiento (o que no se tocan: extraviado). */
    private const CERRADOS = ['archivado', 'entregado', 'acuse_recibido', 'extraviado'];

    public function up(): void
    {
        DB::table('documentos_fisicos')
            ->where('tipo', 'juego_convenio')->where('juego', 3)->where('destino', 'Rectoría')
            ->update(['destino' => 'Archivo de la Unidad Jurídica', 'updated_at' => now()]);

        $expedientes = DB::table('documentos_fisicos')
            ->join('convenios', 'convenios.id', '=', 'documentos_fisicos.convenio_id')
            ->whereIn('convenios.estado', ['en_firmas', 'formalizado'])
            ->where('documentos_fisicos.tipo', 'dictamen_juridico')
            ->whereNotIn('documentos_fisicos.estado', self::CERRADOS)
            ->pluck('documentos_fisicos.id');

        foreach ($expedientes as $id) {
            $this->mover($id, 'archivado', 'Dictamen favorable recibido: el expediente concluye.');
        }

        $juegos = DB::table('documentos_fisicos')
            ->join('convenios', 'convenios.id', '=', 'documentos_fisicos.convenio_id')
            ->where('convenios.estado', 'formalizado')
            ->where('documentos_fisicos.tipo', 'juego_convenio')
            ->whereNotIn('documentos_fisicos.estado', self::CERRADOS)
            ->get(['documentos_fisicos.id', 'documentos_fisicos.juego', 'documentos_fisicos.destino']);

        foreach ($juegos as $j) {
            (int) $j->juego === 1
                ? $this->mover($j->id, 'archivado', 'Convenio formalizado: el juego queda en el archivo de Vinculación.')
                : $this->mover($j->id, 'entregado', 'Convenio formalizado: juego entregado a '.($j->destino ?: ((int) $j->juego === 2 ? 'Empresa' : 'Archivo de la Unidad Jurídica')).'.');
        }
    }

    private function mover(int $id, string $estado, string $nota): void
    {
        DB::table('documentos_fisicos')->where('id', $id)->update([
            'estado' => $estado, 'fecha_estado' => now()->toDateString(), 'fecha_compromiso' => null, 'updated_at' => now(),
        ]);
        DB::table('documento_fisico_movimientos')->insert([
            'documento_fisico_id' => $id, 'estado' => $estado, 'fecha' => now()->toDateString(),
            'nota' => $nota, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Los movimientos quedan en el historial de cada papel
    }
};
