<?php

declare(strict_types=1);

namespace App\Informes;

use App\Models\Personal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Un informe exportable a CSV: sus columnas, la consulta (ya filtrada y limitada al alcance de quien lo pide)
 * y cómo se convierte cada registro en una fila.
 *
 * Filtros que entiende (todos opcionales, validados en FiltrosInformeRequest):
 * carrera_id, estado, ciclo, desde, hasta y, en documentos físicos, vista y q.
 */
abstract class Informe
{
    /** Tipo de la URL => clase del informe. */
    public const TIPOS = [
        'practicas' => InformePracticas::class,
        'postulaciones' => InformePostulaciones::class,
        'convenios' => InformeConvenios::class,
        'programas' => InformeProgramas::class,
        'empresas' => InformeEmpresas::class,
        'documentos-fisicos' => InformeDocumentosFisicos::class,
    ];

    /**
     * Vinculación solo exporta lo suyo; prácticas, postulaciones y programas son del ámbito académico
     * y solo los exporta el administrador.
     */
    public const DE_VINCULACION = ['convenios', 'empresas', 'documentos-fisicos'];

    public function __construct(
        protected Request $filtros,
        protected Personal $personal,
    ) {}

    /** Informe del tipo pedido (404 si no existe). */
    public static function para(string $tipo, Request $filtros, Personal $personal): self
    {
        $clase = self::TIPOS[$tipo] ?? abort(404);

        return new $clase($filtros, $personal);
    }

    /** @return list<string> */
    abstract public function encabezados(): array;

    /** @return Builder<covariant Model> */
    abstract public function consulta(): Builder;

    /**
     * Valores de la fila, en el orden de encabezados(). null = el registro no entra (filtro que se calcula en PHP).
     *
     * @return list<mixed>|null
     */
    abstract public function fila(Model $modelo): ?array;

    /** Valor de texto de un filtro ('' si no viene). */
    protected function filtro(string $clave): string
    {
        return $this->filtros->string($clave)->toString();
    }
}
