<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VistoriaRascunho extends Model
{
    protected $table = 'vistorias_rascunhos';

    protected $fillable = [
        'user_id',
        'ponto_id',
        'lat',
        'lng',
        'context_key',
        'payload',
        'etapa_atual',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'etapa_atual' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ponto, $this> */
    public function ponto(): BelongsTo
    {
        return $this->belongsTo(Ponto::class);
    }
}
