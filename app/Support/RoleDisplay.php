<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class RoleDisplay
{
    private const LABELS = [
        'admin' => 'Admin',
        'supervisor' => 'Supervisor',
        'coordenador' => 'Coordenador',
        'guardas-municipais' => 'Guardas Municipais',
        'agentes-slu' => 'Agentes da SLU',
        'agentes-campo' => 'Agentes de Campo',
    ];

    public static function label(string $roleName): string
    {
        return self::LABELS[$roleName] ?? Str::title(str_replace('-', ' ', $roleName));
    }

    /**
     * Normaliza nome de role para o padrao canonico: lowercase + kebab-case
     * ASCII, sem acentos, sem espacos. Ex: "Agentes da SLU" -> "agentes-slu".
     */
    public static function normalize(string $roleName): string
    {
        return Str::slug($roleName, '-');
    }

    /**
     * Normaliza nome de permissao: lowercase + espacos simples colapsados, sem acento.
     * Mantem espacos por compatibilidade com o padrao "ver vistorias", "criar pontos" etc.
     */
    public static function normalizePermission(string $permissionName): string
    {
        $ascii = Str::ascii($permissionName);
        $lower = mb_strtolower(trim($ascii));

        return preg_replace('/\s+/', ' ', $lower) ?? $lower;
    }
}
