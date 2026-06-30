<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Support\Collection;

enum TipoEquipe: string
{
    case Supervisores = 'supervisores';
    case Coordenadores = 'coordenadores';
    case Gcm = 'gcm';
    case Slu = 'slu';
    case AgentesCampo = 'agentes_campo';
    case Outros = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::Supervisores => 'Supervisores',
            self::Coordenadores => 'Coordenadores',
            self::Gcm => 'GCM',
            self::Slu => 'SLU',
            self::AgentesCampo => 'Agentes de Campo',
            self::Outros => 'Outros',
        };
    }

    /** @return list<self> */
    public static function ordenados(): array
    {
        return [
            self::Supervisores,
            self::Coordenadores,
            self::Gcm,
            self::Slu,
            self::AgentesCampo,
            self::Outros,
        ];
    }

    public static function fromUser(User $user): self
    {
        /** @var Collection<int, string> $roles */
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')
            : $user->getRoleNames();

        $prioridade = [
            'supervisor' => self::Supervisores,
            'coordenador' => self::Coordenadores,
            'guardas-municipais' => self::Gcm,
            'agentes-slu' => self::Slu,
            'agentes-campo' => self::AgentesCampo,
            'agente' => self::AgentesCampo,
        ];

        foreach ($prioridade as $roleName => $tipo) {
            if ($roles->contains($roleName)) {
                return $tipo;
            }
        }

        return self::Outros;
    }
}
