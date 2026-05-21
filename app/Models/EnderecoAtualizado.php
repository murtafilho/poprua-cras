<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @extends \Illuminate\Database\Eloquent\Model<EnderecoAtualizado> */
class EnderecoAtualizado extends Model
{
    use HasFactory;

    protected $table = 'endereco_atualizados';

    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /**
     * Pontos vinculados a este endereço.
     */
    public function pontos(): HasMany
    {
        return $this->hasMany(Ponto::class, 'endereco_atualizado_id');
    }

    /**
     * Acessor para número formatado (inteiro).
     */
    public function getNumeroAttribute(): ?int
    {
        return $this->NUMERO_IMOVEL ? (int) $this->NUMERO_IMOVEL : null;
    }

    /**
     * Acessor para tipo do logradouro.
     */
    public function getTipoAttribute(): ?string
    {
        return $this->SIGLA_TIPO_LOGRADOURO;
    }

    /**
     * Acessor para nome do logradouro.
     */
    public function getLogradouroAttribute(): ?string
    {
        return $this->NOME_LOGRADOURO;
    }

    /**
     * Acessor para bairro (usa o nome popular, mais conhecido).
     */
    public function getBairroAttribute(): ?string
    {
        return $this->NOME_BAIRRO_POPULAR;
    }

    /**
     * Acessor para regional.
     */
    public function getRegionalAttribute(): ?string
    {
        return $this->NOME_REGIONAL;
    }

    /**
     * Acessor para CEP.
     */
    public function getCepAttribute(): ?string
    {
        return $this->attributes['CEP'] ?? null;
    }

    /**
     * Retorna o endereço formatado completo.
     */
    public function getEnderecoCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->tipo,
            $this->logradouro,
            $this->numero ? ', '.$this->numero : null,
            $this->bairro ? ' - '.$this->bairro : null,
        ]);

        return implode(' ', $partes);
    }
}
