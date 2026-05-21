<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends \Illuminate\Database\Eloquent\Model<Morador> */
class Morador extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\MoradorFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'moradores';

    protected $fillable = [
        'ponto_atual_id',
        'nome_social',
        'nome_registro',
        'apelido',
        'genero',
        'observacoes',
        'documento',
        'contato',
    ];

    public function pontoAtual(): BelongsTo
    {
        return $this->belongsTo(Ponto::class, 'ponto_atual_id');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(MoradorHistorico::class)->orderByDesc('data_entrada');
    }

    /**
     * Retorna todos os pontos onde o morador já esteve
     */
    public function pontos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Ponto::class, 'morador_historicos')
            ->withPivot(['vistoria_entrada_id', 'vistoria_saida_id', 'data_entrada', 'data_saida'])
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fotos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('fotos')
            ->queued();

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->format('webp')
            ->quality(85)
            ->performOnCollections('fotos')
            ->queued();
    }
}
