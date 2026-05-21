<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read string $nome */
class CaracteristicaAbrigo extends Model
{
    protected $table = 'caracteristica_abrigo';

    public $timestamps = false;

    protected $fillable = [
        'caracteristica_abrigo',
    ];

    public function pontos(): HasMany
    {
        return $this->hasMany(Ponto::class, 'caracteristica_abrigo_id');
    }

    /**
     * Accessor para o nome da característica
     */
    public function getNomeAttribute(): string
    {
        return $this->caracteristica_abrigo;
    }
}
