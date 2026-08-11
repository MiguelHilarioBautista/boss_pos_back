<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solo lectura desde la app: las filas las inserta el trigger
 * trg_productos_au_historial (ver migracion de M0) cuando cambia precio o
 * costo_neto en productos. No tiene metodos de escritura en ningun
 * controlador.
 */
class HistorialPrecio extends Model
{
    protected $table = 'historial_precios';

    const CREATED_AT = 'fecha';
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'precio_anterior' => 'decimal:2',
            'precio_nuevo' => 'decimal:2',
            'costo_anterior' => 'decimal:2',
            'costo_nuevo' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
