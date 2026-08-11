<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'unidades_medida';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = ['nombre', 'codigo', 'permite_decimales', 'activo'];

    protected function casts(): array
    {
        return [
            'permite_decimales' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_unidad_medida');
    }
}
