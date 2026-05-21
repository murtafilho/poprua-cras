<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read string $nome */
class TipoAbrigoDesmontado extends Model
{
    protected $table = 'tipo_abrigo_desmontado';

    public $timestamps = false;

    protected $fillable = ['tipo_abrigo'];

    public function vistorias(): HasMany
    {
        return $this->hasMany(Vistoria::class, 'tipo_abrigo_desmontado_id');
    }

    public function getNomeAttribute(): string
    {
        return $this->tipo_abrigo;
    }
}
