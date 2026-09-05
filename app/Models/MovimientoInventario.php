<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kardex append-only (bloqueado por trg_movinv_bu/bd_bloquear desde M0).
 * SOLO LECTURA desde la app: ningun controlador expone update/delete, y el
 * unico INSERT real lo hace InventarioService por query builder directo
 * (no Model::create), para que quede clarisimo que este modelo no es un
 * punto de escritura.
 */
class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    const CREATED_AT = 'fecha';
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'stock_anterior' => 'decimal:3',
            'stock_nuevo' => 'decimal:3',
            'costo_prom_anterior' => 'decimal:2',
            'costo_prom_nuevo' => 'decimal:2',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
