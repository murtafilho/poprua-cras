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

    /** @return HasMany<Vistoria, $this> */
    public function vistorias(): HasMany
    {
        return $this->hasMany(Vistoria::class, 'tipo_abordagem_id');
    }

    public function getNomeAttribute(): string
    {
        return $this->tipo;
    }

    public function isComunicacaoZeladoria(): bool
    {
        return self::tipoEhComunicacaoZeladoria($this->tipo);
    }

    public static function tipoEhComunicacaoZeladoria(?string $tipo): bool
    {
        if ($tipo === null || $tipo === '') {
            return false;
        }

        $normalizado = mb_strtolower($tipo);

        return str_contains($normalizado, 'comunic') && str_contains($normalizado, 'zeladoria');
    }
}
