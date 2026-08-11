<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RN-M1-05: las tasas no se editan (no hay metodo update en el controlador
 * a proposito) — se crea un impuesto nuevo cuando cambia la ley.
 */
class Impuesto extends Model
{
    use HasFactory;

    protected $table = 'impuestos';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = ['codigo', 'nombre', 'tasa', 'activo'];

    protected function casts(): array
    {
        return [
            'tasa' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'impuesto_id');
    }
}
