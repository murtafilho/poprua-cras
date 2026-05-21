<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ponto;
use App\Models\User;

class PontoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ponto $ponto): bool
    {
        return true;
    }

    public function update(User $user, Ponto $ponto): bool
    {
        return false;
    }
}
