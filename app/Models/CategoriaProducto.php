<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProducto extends Model
{
    use HasFactory;

    protected $table = 'categoria_productos';

    const CREATED_AT = 'creado_fecha';
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = ['nombre', 'categoria_padre_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'categoria_padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'categoria_padre_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria');
    }
}
