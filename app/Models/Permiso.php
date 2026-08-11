<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';

    public $timestamps = false;

    protected $fillable = ['codigo', 'modulo', 'nombre'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rol_permiso', 'permiso_id', 'rol_id');
    }
}
