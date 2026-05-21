<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read string $nome */
class TipoAbordagem extends Model
{
    protected $table = 'tipo_abordagem';

    public $timestamps = false;

    protected $fillable = ['tipo'];

    public function vistorias(): HasMany
    {
        return $this->hasMany(Vistoria::class, 'tipo_abordagem_id');
    }

    public function getNomeAttribute(): string
    {
        return $this->tipo;
    }
}
