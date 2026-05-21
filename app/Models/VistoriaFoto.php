<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VistoriaFoto extends Model
{
    protected $table = 'vistoria_fotos';

    protected $fillable = [
        'vistoria_id',
        'caminho',
        'nome_original',
        'tamanho',
        'mime_type',
        'ordem',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function vistoria(): BelongsTo
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }

    /**
     * Retorna a URL completa da foto
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->caminho);
    }
}
