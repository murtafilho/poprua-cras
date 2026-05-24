<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class ProfileService
{
    /**
     * Atualizar dados do perfil do usuario.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): void
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    /** Excluir conta do usuario. */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
