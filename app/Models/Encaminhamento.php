<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Encaminhamento extends Model
{
    protected $table = 'encaminhamentos';

    public $timestamps = false;

    protected $fillable = ['encaminhamento'];

    public function getNomeAttribute(): string
    {
        return $this->encaminhamento;
    }
}
