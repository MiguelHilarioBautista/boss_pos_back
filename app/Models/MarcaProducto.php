<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarcaProducto extends Model
{
    use HasFactory;

    protected $table = 'marca_producto';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_marca');
    }
}
