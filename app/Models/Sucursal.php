<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'nombre', 'codigo', 'direccion', 'telefono', 'email', 'logo_url',
        'numero_exterior', 'codigo_postal', 'pais', 'estado',
        'zona_frontera', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'zona_frontera' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Se guarda en BD como ruta relativa ("/storage/logoempresa/x.png",
     * portable entre entornos), pero se expone siempre absoluta: si el
     * frontend usa el valor tal cual en un <img>, el navegador lo resuelve
     * contra SU propio origen (localhost:5173, por ejemplo), no el de esta
     * API, y la imagen nunca carga aunque el archivo si se haya guardado.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value && str_starts_with($value, '/') ? url($value) : $value,
        );
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
