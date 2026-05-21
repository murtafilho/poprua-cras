<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MembroEquipe extends Model
{
    /** @use HasFactory<Factory<MembroEquipe>> */
    use HasFactory;

    protected $table = 'membros_equipe';

    protected $fillable = [
        'nome',
        'matricula',
        'email',
        'equipe',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Vistoria, $this> */
    public function vistorias(): BelongsToMany
    {
        return $this->belongsToMany(Vistoria::class, 'vistoria_participantes', 'membro_equipe_id', 'vistoria_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<MembroEquipe>  $query
     * @return Builder<MembroEquipe>
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * @param  Builder<MembroEquipe>  $query
     * @return Builder<MembroEquipe>
     */
    public function scopeEquipe(Builder $query, string $equipe): Builder
    {
        return $query->where('equipe', $equipe);
    }
}
