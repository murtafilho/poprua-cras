<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VistoriaRascunho;

class VistoriaRascunhoPolicy
{
    public function view(User $user, VistoriaRascunho $rascunho): bool
    {
        return $rascunho->user_id === $user->id;
    }

    public function update(User $user, VistoriaRascunho $rascunho): bool
    {
        return $rascunho->user_id === $user->id;
    }

    public function delete(User $user, VistoriaRascunho $rascunho): bool
    {
        return $rascunho->user_id === $user->id;
    }
}
