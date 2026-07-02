<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Parametro extends Model
{
    protected $table = 'parametros';

    protected $primaryKey = 'chave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['chave', 'valor', 'tipo', 'grupo', 'descricao'];

    public static function get(string $chave, mixed $default = null): mixed
    {
        $param = Cache::remember(
            "param:{$chave}",
            3600,
            fn () => static::find($chave)
        );

        if (! $param) {
            return $default;
        }

        return match ($param->tipo) {
            'integer' => (int) $param->valor,
            'float' => (float) $param->valor,
            'boolean' => filter_var($param->valor, FILTER_VALIDATE_BOOLEAN),
            default => $param->valor,
        };
    }

    public static function set(string $chave, mixed $valor): void
    {
        static::where('chave', $chave)->update([
            'valor' => (string) $valor,
            'updated_at' => now(),
        ]);

        static::forgetCache($chave);
    }

    public static function forgetCache(string $chave): void
    {
        Cache::forget("param:{$chave}");
    }
}
