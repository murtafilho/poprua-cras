<?php

namespace App\Models;

use Database\Factories\PontoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Ponto extends Model
{
    /** @use HasFactory<PontoFactory> */
    use HasFactory, SoftDeletes;

    public const COMPLEXIDADE_SQL = '(COALESCE(v.resistencia::int, 0) + COALESCE(v.num_reduzido::int, 0) + COALESCE(v.casal::int, 0) + COALESCE(v.catador_reciclados::int, 0) + COALESCE(v.fixacao_antiga::int, 0) + COALESCE(v.excesso_objetos::int, 0) + COALESCE(v.trafico_ilicitos::int, 0) + COALESCE(v.crianca_adolescente::int, 0) + COALESCE(v.idosos::int, 0) + COALESCE(v.gestante::int, 0) + COALESCE(v.lgbtqiapn::int, 0) + COALESCE(v.cena_uso_caracterizada::int, 0) + COALESCE(v.deficiente::int, 0) + COALESCE(v.agrupamento_quimico::int, 0) + COALESCE(v.saude_mental::int, 0) + COALESCE(v.animais::int, 0))';

    protected $table = 'pontos';

    protected $fillable = [
        'numero',
        'complemento',
        'observacao',
        'endereco_atualizado_id',
        'caracteristica_abrigo_id',
        'lat',
        'lng',
    ];

    /** @return BelongsTo<EnderecoAtualizado, $this> */
    public function enderecoAtualizado(): BelongsTo
    {
        return $this->belongsTo(EnderecoAtualizado::class, 'endereco_atualizado_id');
    }

    /** @return HasMany<Vistoria, $this> */
    public function vistorias(): HasMany
    {
        return $this->hasMany(Vistoria::class, 'ponto_id');
    }

    /** @return HasOne<Vistoria, $this> */
    public function ultimaVistoria(): HasOne
    {
        return $this->hasOne(Vistoria::class, 'ponto_id')->latestOfMany();
    }

    /** @return BelongsTo<CaracteristicaAbrigo, $this> */
    public function caracteristicaAbrigo(): BelongsTo
    {
        return $this->belongsTo(CaracteristicaAbrigo::class, 'caracteristica_abrigo_id');
    }

    /** @return HasMany<Morador, $this> */
    public function moradores(): HasMany
    {
        return $this->hasMany(Morador::class, 'ponto_atual_id');
    }

    /** @return HasMany<MoradorHistorico, $this> */
    public function historicoMoradores(): HasMany
    {
        return $this->hasMany(MoradorHistorico::class, 'ponto_id')->orderByDesc('data_entrada');
    }

    /**
     * Scope: pontos dentro de uma bounding box geográfica
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeInBounds(Builder $query, float $north, float $south, float $east, float $west): Builder
    {
        return $query->whereRaw('geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$west, $south, $east, $north]);
    }

    /**
     * Scope: pontos georreferenciados (com coordenadas válidas)
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeGeorreferenciado(Builder $query): Builder
    {
        return $query->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('lat', '!=', 0)
            ->where('lng', '!=', 0);
    }

    /**
     * Scope: pontos não georreferenciados (sem coordenadas válidas)
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeNaoGeorreferenciado(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('lat')
                ->orWhereNull('lng')
                ->orWhere('lat', '=', 0)
                ->orWhere('lng', '=', 0);
        });
    }

    /**
     * Scope: pontos com endereço vinculado
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeComEndereco(Builder $query): Builder
    {
        return $query->whereNotNull('endereco_atualizado_id');
    }

    /**
     * Scope: filtro por regional
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeRegional(Builder $query, string $regional): Builder
    {
        return $query->whereHas('enderecoAtualizado', function ($q) use ($regional) {
            $q->where('NOME_REGIONAL', $regional);
        });
    }

    /**
     * Scope: filtro por bairro
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeBairro(Builder $query, string $bairro): Builder
    {
        return $query->whereHas('enderecoAtualizado', function ($q) use ($bairro) {
            $q->where('NOME_BAIRRO_OFICIAL', 'like', "%{$bairro}%");
        });
    }

    /**
     * Scope: filtro por logradouro
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeLogradouro(Builder $query, string $logradouro): Builder
    {
        return $query->whereHas('enderecoAtualizado', function ($q) use ($logradouro) {
            $q->where('NOME_LOGRADOURO', 'like', "%{$logradouro}%");
        });
    }

    /**
     * Retorna o número do endereço
     */
    public function getNumeroEnderecoAttribute(): ?string
    {
        if ($this->relationLoaded('enderecoAtualizado') && $this->enderecoAtualizado instanceof EnderecoAtualizado) {
            return (string) $this->enderecoAtualizado->numero;
        }

        return $this->numero;
    }

    /**
     * Retorna o endereço (alias para enderecoAtualizado)
     */
    public function getEnderecoAttribute(): ?EnderecoAtualizado
    {
        if ($this->relationLoaded('enderecoAtualizado')) {
            /** @var EnderecoAtualizado|null */
            $endereco = $this->enderecoAtualizado;

            return $endereco;
        }

        return null;
    }

    /**
     * Retorna total de vistorias do ponto
     */
    public function getTotalVistoriasAttribute(): int
    {
        if ($this->relationLoaded('vistorias')) {
            return $this->vistorias->count();
        }

        return $this->vistorias()->count();
    }

    /**
     * Calcula complexidade baseada na última vistoria
     */
    public function getComplexidadeAttribute(): int
    {
        /** @var Vistoria|null $vistoria */
        $vistoria = $this->relationLoaded('ultimaVistoria')
            ? $this->ultimaVistoria
            : $this->ultimaVistoria()->first();

        if (! $vistoria) {
            return 0;
        }

        return (int) $vistoria->resistencia
            + (int) $vistoria->num_reduzido
            + (int) $vistoria->casal
            + (int) $vistoria->catador_reciclados
            + (int) $vistoria->fixacao_antiga
            + (int) $vistoria->excesso_objetos
            + (int) $vistoria->trafico_ilicitos
            + (int) $vistoria->crianca_adolescente
            + (int) $vistoria->idosos
            + (int) $vistoria->gestante
            + (int) $vistoria->lgbtqiapn
            + (int) $vistoria->cena_uso_caracterizada
            + (int) $vistoria->deficiente
            + (int) $vistoria->agrupamento_quimico
            + (int) $vistoria->saude_mental
            + (int) $vistoria->animais;
    }

    /**
     * Scope: pontos próximos a uma coordenada (dentro de $distancia metros)
     *
     * @param  Builder<Ponto>  $query
     * @return Builder<Ponto>
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, float $distancia = 50): Builder
    {
        return $query
            ->whereRaw(
                'ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) < ?',
                [$lng, $lat, $distancia]
            )
            ->orderByRaw(
                'ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                [$lng, $lat]
            );
    }

    /**
     * Atualiza a coluna geom (PostGIS) a partir de lat/lng.
     */
    public function updateGeom(): void
    {
        if ($this->lat && $this->lng) {
            DB::statement(
                'UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$this->lng, $this->lat, $this->id]
            );
        }
    }

    /**
     * Retorna o endereço formatado completo
     */
    public function getEnderecoCompletoAttribute(): string
    {
        /** @var EnderecoAtualizado|null $enderecoAtualizado */
        $enderecoAtualizado = $this->relationLoaded('enderecoAtualizado') ? $this->enderecoAtualizado : null;

        if (! $enderecoAtualizado) {
            return $this->complemento ?? '';
        }

        $endereco = $enderecoAtualizado->endereco_completo;

        if ($this->complemento) {
            $endereco .= " ({$this->complemento})";
        }

        return $endereco;
    }
}
