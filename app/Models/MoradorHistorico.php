<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @extends \Illuminate\Database\Eloquent\Model<MoradorHistorico> */
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

    public function morador(): BelongsTo
    {
        return $this->belongsTo(Morador::class);
    }

    public function ponto(): BelongsTo
    {
        return $this->belongsTo(Ponto::class);
    }

    public function vistoriaEntrada(): BelongsTo
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_entrada_id');
    }

    public function vistoriaSaida(): BelongsTo
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_saida_id');
    }
}
