<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCompra extends Model
{
    protected $table = 'detalle_compra';

    public $timestamps = false;

    protected $fillable = [
        'compra_id', 'producto_id', 'numero_renglon', 'descripcion',
        'cantidad', 'cantidad_recibida', 'costo_unitario', 'descuento_monto', 'tasa_iva',
    ];

    // importe_neto / impuesto_monto son columnas GENERATED reales (igual
    // que margen_unitario en Producto, M1): se leen normal, no son accessors.

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_recibida' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'tasa_iva' => 'decimal:4',
            'importe_neto' => 'decimal:2',
            'impuesto_monto' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
