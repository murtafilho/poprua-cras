<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vistoria;

class VistoriaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete' && $user->can('excluir vistorias')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vistoria $vistoria): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vistoria $vistoria): bool
    {
        if ($vistoria->finalizada || $vistoria->cancelada) {
            return false;
        }

        return $vistoria->user_id === $user->id;
    }

    public function reativar(User $user, Vistoria $vistoria): bool
    {
        return $vistoria->finalizada && ! $vistoria->cancelada && $user->can('reativar vistorias');
    }

    public function cancelar(User $user, Vistoria $vistoria): bool
    {
        if ($vistoria->cancelada) {
            return false;
        }

        if ($vistoria->finalizada) {
            return $user->can('cancelar vistorias');
        }

        return $vistoria->user_id === $user->id;
    }

    public function delete(User $user, Vistoria $vistoria): bool
    {
        return $vistoria->user_id === $user->id;
    }

    public function restore(User $user, Vistoria $vistoria): bool
    {
        return $user->hasPermissionTo('excluir vistorias');
    }

    public function forceDelete(User $user, Vistoria $vistoria): bool
    {
        return false;
    }
}
