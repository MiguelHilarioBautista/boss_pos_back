<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = [
        'rol_id',
        'usuario',
        'password_hash',
        'pin_hash',
        'nombre',
        'apellidos',
        'numero_empleado',
        'email',
        'telefono',
        'activo',
        'debe_cambiar_pass',
        'intentos_fallidos',
        'bloqueado_hasta',
        'ultimo_acceso',
        'password_actualizado',
    ];

    protected $hidden = [
        'password_hash',
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'debe_cambiar_pass' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password_actualizado' => 'datetime',
        ];
    }

    /**
     * El esquema usa password_hash, no la columna estandar `password` de Laravel.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * El esquema no tiene remember_token: "recordarme" no aplica (no se usa).
     */
    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // no-op: no existe la columna remember_token en `usuarios`.
    }

    public function getRememberTokenName()
    {
        return '';
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'usuario_sucursal', 'usuario_id', 'sucursal_id')
            ->withPivot('es_principal');
    }

    /**
     * Codigos de permiso que otorga el rol del usuario, via rol_permiso.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        if (! isset($this->permisosCache)) {
            $this->permisosCache = Permiso::query()
                ->join('rol_permiso', 'rol_permiso.permiso_id', '=', 'permisos.id')
                ->where('rol_permiso.rol_id', $this->rol_id)
                ->pluck('permisos.codigo')
                ->all();
        }

        return $this->permisosCache;
    }

    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->permisos(), true);
    }

    /** @var array<int, string>|null */
    private ?array $permisosCache = null;
}
