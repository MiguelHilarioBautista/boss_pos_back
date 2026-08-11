<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuracion';

    const CREATED_AT = null;
    const UPDATED_AT = 'modificado_fecha';

    protected $fillable = [
        'clave', 'valor', 'tipo_dato', 'descripcion', 'sucursal_id', 'modificable', 'modificado_por',
    ];

    protected function casts(): array
    {
        return [
            'modificable' => 'boolean',
        ];
    }

    /**
     * Lee una clave de configuracion, casteada segun su tipo_dato.
     * sucursal_id = null busca el valor global.
     */
    public static function valor(string $clave, mixed $default = null, ?int $sucursalId = null): mixed
    {
        $config = static::query()
            ->where('clave', $clave)
            ->where('sucursal_id', $sucursalId)
            ->first();

        if (! $config) {
            return $default;
        }

        return match ($config->tipo_dato) {
            'INT' => (int) $config->valor,
            'DECIMAL' => (float) $config->valor,
            'BOOLEAN' => filter_var($config->valor, FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($config->valor, true),
            default => $config->valor,
        };
    }
}
