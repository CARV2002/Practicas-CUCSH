<?php
namespace App\Enums;

use App\Enums\Concerns\TieneTransiciones;


enum PostulacionEstado: string
{
    use TieneTransiciones;
    case Pendiente = 'pendiente';
    case CartaPresentacionEmitida = 'carta_presentacion_emitida';
    case CartaAceptacionRecibida = 'carta_aceptacion_recibida';
    case Aceptada = 'aceptada';       // ya generó la práctica
    case Rechazada = 'rechazada';
    case Cancelada = 'cancelada';

    /** Estados que ocupan un cupo del programa. */
    public static function ocupanCupo(): array
    {
        return [self::CartaPresentacionEmitida, self::CartaAceptacionRecibida, self::Aceptada];
    }

    /** Estados "en trámite": el alumno ya se comprometió con UNA empresa. */
    public static function enTramite(): array
    {
        return [self::CartaPresentacionEmitida, self::CartaAceptacionRecibida];
    }

    /** Postulaciones que se cancelan solas cuando el alumno activa su práctica. */
    public static function abiertas(): array
    {
        return [self::Pendiente, self::CartaPresentacionEmitida];
    }

    /** @return string[] */
    public static function valores(array $casos): array
    {
        return array_map(fn(self $c) => $c->value, $casos);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de validación',
            self::CartaPresentacionEmitida => 'Carta de presentación emitida',
            self::CartaAceptacionRecibida => 'Carta de aceptación recibida',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function transiciones(): array
    {
        return match ($this) {
            self::Pendiente => [self::CartaPresentacionEmitida, self::Rechazada, self::Cancelada],
            self::CartaPresentacionEmitida => [self::CartaAceptacionRecibida, self::Rechazada, self::Cancelada],
            self::CartaAceptacionRecibida => [self::Aceptada, self::Rechazada, self::Cancelada],
            self::Aceptada => [self::Cancelada],          // solo cuando se cancela su práctica
            self::Rechazada, self::Cancelada => [],
        };
    }
}
