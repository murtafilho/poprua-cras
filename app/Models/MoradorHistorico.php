<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoradorHistorico extends Model
{
    protected $table = 'morador_historicos';

    protected $fillable = [
        'morador_id',
        'ponto_id',
        'vistoria_entrada_id',
        'vistoria_saida_id',
        'data_entrada',
        'data_saida',
    ];

    protected function casts(): array
    {
        return [
            'data_entrada' => 'datetime',
            'data_saida' => 'datetime',
        ];
    }

    /** @return BelongsTo<Morador, $this> */
    public function morador(): BelongsTo
    {
        return $this->belongsTo(Morador::class);
    }

    /** @return BelongsTo<Ponto, $this> */
    public function ponto(): BelongsTo
    {
        return $this->belongsTo(Ponto::class);
    }

    /** @return BelongsTo<Vistoria, $this> */
    public function vistoriaEntrada(): BelongsTo
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_entrada_id');
    }

    /** @return BelongsTo<Vistoria, $this> */
    public function vistoriaSaida(): BelongsTo
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_saida_id');
    }
}
