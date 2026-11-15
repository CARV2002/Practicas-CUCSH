<?php

namespace App\Observers;

use App\Enums\PostulacionEstado;
use App\Models\Postulacion;
use Illuminate\Validation\ValidationException;

class PostulacionObserver
{
    public function updating(Postulacion $postulacion): void
    {
        if (! $postulacion->isDirty('estado')) {
            return;
        }

        $desde = $postulacion->getOriginal('estado');

        if ($desde instanceof PostulacionEstado && ! $desde->puedePasarA($postulacion->estado)) {
            throw ValidationException::withMessages([
                'postulacion' => "Una postulación «{$desde->label()}» no puede pasar a «{$postulacion->estado->label()}».",
            ]);
        }
    }
}