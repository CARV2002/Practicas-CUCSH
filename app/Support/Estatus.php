<?php

namespace App\Support;

/**
 * Colores pastel de los estatus de trámites (DESIGN_SYSTEM.md §1.4).
 *
 * Cada enum de estado declara su "familia" con familia(); aquí se traduce a clases de Tailwind.
 * Las clases están escritas completas para que Tailwind las detecte al compilar.
 */
final class Estatus
{
    public const FAMILIAS = [
        'gris' => ['chip' => 'bg-slate-50 text-slate-700 ring-slate-200', 'punto' => 'bg-slate-400'],
        'cielo' => ['chip' => 'bg-sky-50 text-sky-800 ring-sky-200', 'punto' => 'bg-sky-400'],
        'lavanda' => ['chip' => 'bg-violet-50 text-violet-800 ring-violet-200', 'punto' => 'bg-violet-400'],
        'indigo' => ['chip' => 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'punto' => 'bg-indigo-400'],
        'ambar' => ['chip' => 'bg-amber-50 text-amber-800 ring-amber-200', 'punto' => 'bg-amber-400'],
        'menta' => ['chip' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'punto' => 'bg-emerald-500'],
        'turquesa' => ['chip' => 'bg-teal-50 text-teal-800 ring-teal-200', 'punto' => 'bg-teal-500'],
        'rosa' => ['chip' => 'bg-rose-50 text-rose-800 ring-rose-200', 'punto' => 'bg-rose-500'],
        'piedra' => ['chip' => 'bg-stone-100 text-stone-600 ring-stone-200', 'punto' => 'bg-stone-400'],
    ];

    public static function chip(string $familia): string
    {
        return (self::FAMILIAS[$familia] ?? self::FAMILIAS['gris'])['chip'];
    }

    public static function punto(string $familia): string
    {
        return (self::FAMILIAS[$familia] ?? self::FAMILIAS['gris'])['punto'];
    }

    /** Familia de un enum de estado (o 'gris' si no la declara). */
    public static function familiaDe(mixed $estado): string
    {
        return is_object($estado) && method_exists($estado, 'familia') ? $estado->familia() : 'gris';
    }

    /** Texto visible del estado: label() si existe; si no, el valor legible. */
    public static function etiquetaDe(mixed $estado): string
    {
        if (is_object($estado) && method_exists($estado, 'label')) {
            return $estado->label();
        }

        $valor = $estado instanceof \BackedEnum ? (string) $estado->value : (string) $estado;

        return ucfirst(str_replace('_', ' ', $valor));
    }
}
