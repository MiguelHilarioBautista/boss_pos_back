<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ojo: la columna real es `estatus`, no `estado` como la llama el PDF de M3.
 */
class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = [
        'sucursal_id', 'proveedor_id', 'usuario_id', 'folio_documento',
        'fecha_documento', 'fecha_recepcion', 'subtotal', 'descuento_total',
        'impuesto_total', 'total', 'estatus', 'es_credito', 'fecha_vencimiento',
        'notas', 'cancelada_por', 'cancelada_fecha', 'cancelada_motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_documento' => 'date',
            'fecha_recepcion' => 'datetime',
            'fecha_vencimiento' => 'date',
            'cancelada_fecha' => 'datetime',
            'subtotal' => 'decimal:2',
            'descuento_total' => 'decimal:2',
            'impuesto_total' => 'decimal:2',
            'total' => 'decimal:2',
            'es_credito' => 'boolean',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(DetalleCompra::class, 'compra_id')->orderBy('numero_renglon');
    }
}
