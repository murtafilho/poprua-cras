<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read string $nome */
class ResultadoAcao extends Model
{
    protected $table = 'resultados_acoes';

    public $timestamps = false;

    protected $fillable = ['resultado'];

    public function vistorias(): HasMany
    {
        return $this->hasMany(Vistoria::class, 'resultado_acao_id');
    }

    public function getNomeAttribute(): string
    {
        return $this->resultado;
    }
}
