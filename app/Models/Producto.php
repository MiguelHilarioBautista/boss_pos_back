<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = [
        'tipo_prod_serv', 'nombre', 'sku', 'codigo_barras', 'descripcion',
        'precio', 'costo_neto', 'impuesto_id', 'controla_stock', 'imagen_url',
        'activo', 'id_categoria', 'id_marca', 'id_unidad_medida',
    ];

    // margen_unitario y margen_pct son columnas GENERATED (VIRTUAL) reales en
    // la tabla: MySQL las calcula solo, Eloquent las lee como cualquier otra
    // columna al hacer SELECT * (no son accessors, no van en $appends).

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'costo_neto' => 'decimal:2',
            'margen_unitario' => 'decimal:2',
            'margen_pct' => 'decimal:4',
            'controla_stock' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'id_categoria');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(MarcaProducto::class, 'id_marca');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_medida');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(HistorialPrecio::class, 'producto_id')->latest('fecha');
    }
}
