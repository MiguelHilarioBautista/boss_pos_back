<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = [
        'nombre', 'codigo', 'direccion', 'telefono', 'zona_frontera', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'zona_frontera' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_sucursal', 'sucursal_id', 'usuario_id')
            ->withPivot('es_principal');
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class, 'sucursal_id');
    }
}
