<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Rol;
use App\Models\Concerns\Auditable;
use App\Support\Ayuda;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso de cualquier rol; su perfil está en Alumno, Empresa o Personal.
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'activo', 'aviso_privacidad_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'aviso_privacidad_at' => 'datetime',
            'foto_actualizada_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /** @return HasOne<Alumno, $this> */
    public function alumno(): HasOne
    {
        return $this->hasOne(Alumno::class);
    }

    /** @return HasOne<Empresa, $this> */
    public function empresa(): HasOne
    {
        return $this->hasOne(Empresa::class);
    }

    /** @return HasOne<Personal, $this> */
    public function personal(): HasOne
    {
        return $this->hasOne(Personal::class);
    }

    public function auditoriaEtiqueta(): string
    {
        return 'Usuario';
    }

    public function auditoriaNombre(): ?string
    {
        return $this->email;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto')
            ->useDisk('local')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->performOnCollections('foto')
            ->nonQueued()
            ->fit(Fit::Crop, 160, 160);
    }

    /** URL protegida de la foto (null si no tiene). El ?v= evita caché vieja al cambiarla. */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto_actualizada_at
            ? route('usuarios.foto', ['user' => $this->id, 'v' => $this->foto_actualizada_at->timestamp])
            : null);
    }

    protected function iniciales(): Attribute
    {
        return Attribute::get(function () {
            $partes = preg_split('/\s+/', trim($this->name));

            return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
        });
    }

    /** Rol principal del usuario (o null si no tiene). */
    public function rolPrincipal(): ?Rol
    {
        return Rol::tryFrom((string) $this->getRoleNames()->first());
    }

    /**
     * ¿La cuenta tiene el perfil que exige su rol? Un alumno o empresa cuyo perfil
     * se borró (p. ej. directo en la base) ya no es una cuenta válida y no puede entrar.
     */
    public function tienePerfil(): bool
    {
        $rol = $this->rolPrincipal();

        return match (true) {
            $rol === null => true,   // sin rol: no entra a ningún panel; esta regla solo cuida el perfil de su rol
            $rol === Rol::Alumno => $this->alumno()->exists(),
            $rol === Rol::Empresa => $this->empresa()->exists(),
            // El personal sin perfil ya recibe un 403 en su panel (RoleMiddleware)
            default => true,
        };
    }

    /** ¿Ve el Centro de ayuda? Vinculación y el Administrador no (operan el sistema). */
    public function tieneAyuda(): bool
    {
        return Ayuda::disponiblePara($this->rolPrincipal());
    }
}
