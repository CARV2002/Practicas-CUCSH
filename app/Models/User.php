<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements MustVerifyEmail, HasMedia
{
    use HasFactory, HasRoles, Notifiable, Auditable, InteractsWithMedia;

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

    public function alumno(): HasOne
    {
        return $this->hasOne(Alumno::class);
    }

    public function empresa(): HasOne
    {
        return $this->hasOne(Empresa::class);
    }

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
            ->fit(Fit::Crop, 160, 160)
            ->performOnCollections('foto')
            ->nonQueued();
    }

    /** URL protegida de la foto (null si no tiene). El ?v= evita caché vieja al cambiarla. */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn() => $this->foto_actualizada_at
            ? route('usuarios.foto', ['user' => $this->id, 'v' => $this->foto_actualizada_at->timestamp])
            : null);
    }

    protected function iniciales(): Attribute
    {
        return Attribute::get(function () {
            $partes = preg_split('/\s+/', trim($this->name));

            return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
        });
    }

    /** Rol principal del usuario (o null si no tiene). */
    public function rolPrincipal(): ?\App\Enums\Rol
    {
        return \App\Enums\Rol::tryFrom((string) $this->roles->first()?->name);
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
            $rol === \App\Enums\Rol::Alumno => $this->alumno()->exists(),
            $rol === \App\Enums\Rol::Empresa => $this->empresa()->exists(),
            // El personal sin perfil ya recibe un 403 en su panel (RoleMiddleware)
            default => true,
        };
    }

    /** ¿Ve el Centro de ayuda? Vinculación y el Administrador no (operan el sistema). */
    public function tieneAyuda(): bool
    {
        return \App\Support\Ayuda::disponiblePara($this->rolPrincipal());
    }

}
