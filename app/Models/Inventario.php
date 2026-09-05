<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PK compuesta (producto_id, sucursal_id) — Eloquent no maneja bien PKs
 * compuestas para find()/route-binding, asi que este modelo se usa sobre
 * todo para lectura (listados, filtros). Las escrituras reales (con el
 * SELECT ... FOR UPDATE + calculo de costo promedio) viven en
 * InventarioService, por query builder directo.
 */
class Inventario extends Model
{
    protected $table = 'inventario';

    // PK real es compuesta (producto_id, sucursal_id); Eloquent no la
    // soporta nativamente. Se fija producto_id como PK nominal solo para
    // que el framework no truene internamente — este modelo nunca llama
    // find()/save() (ver docblock de la clase), asi que la falta de
    // unicidad real de esa "PK" nunca se ejercita.
    protected $primaryKey = 'producto_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'producto_id', 'sucursal_id', 'stock', 'stock_minimo', 'stock_maximo', 'costo_promedio', 'ubicacion',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'stock_maximo' => 'decimal:3',
            'costo_promedio' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
