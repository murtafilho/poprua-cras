@extends('layouts.app')

@section('title', 'Matriz de Roles e Permissoes')

@push('styles')
<style>
    .matriz-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        margin-bottom: var(--space-6);
    }
    .matriz-table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--text-sm);
        white-space: nowrap;
        background: var(--bg-primary);
    }
    .matriz-table th,
    .matriz-table td {
        padding: var(--space-2) var(--space-3);
        border-bottom: 1px solid var(--border-color);
        border-right: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .matriz-table th:last-child,
    .matriz-table td:last-child { border-right: none; }
    /* Header de roles */
    .th-role {
        background: var(--bg-secondary);
        font-weight: var(--font-semibold);
        text-align: center;
        font-size: var(--text-xs);
        color: var(--text-secondary);
        letter-spacing: 0.02em;
        min-width: 110px;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .th-corner {
        background: var(--bg-secondary);
        position: sticky;
        top: 0;
        left: 0;
        z-index: 3;
        min-width: 200px;
    }
    /* Header de grupo */
    .tr-grupo td {
        background: var(--bg-tertiary, #1a2030);
        font-weight: var(--font-semibold);
        font-size: var(--text-xs);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--accent-primary);
        padding: var(--space-1) var(--space-3);
        border-bottom: 1px solid var(--border-color);
    }
    /* Permissao label */
    .td-permissao {
        font-size: var(--text-xs);
        color: var(--text-primary);
        background: var(--bg-primary);
        position: sticky;
        left: 0;
        z-index: 1;
        border-right: 2px solid var(--border-color) !important;
        padding-left: var(--space-4) !important;
    }
    /* Cell check */
    .td-check {
        text-align: center;
        background: var(--bg-primary);
    }
    .check-yes {
        color: #22c55e;
        font-size: 18px;
        line-height: 1;
    }
    .check-no {
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1;
        opacity: 0.3;
    }
    /* Hover row */
    .matriz-table tbody tr:not(.tr-grupo):hover .td-permissao,
    .matriz-table tbody tr:not(.tr-grupo):hover .td-check {
        background: var(--bg-hover, rgba(255,255,255,0.04));
    }
    /* Badge role no header */
    .role-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 4px;
    }
    .role-admin    { background: rgba(239,68,68,0.15); color: #ef4444; }
    .role-sup      { background: rgba(168,85,247,0.15); color: #a855f7; }
    .role-coord    { background: rgba(59,130,246,0.15); color: #3b82f6; }
    .role-guarda   { background: rgba(34,197,94,0.15); color: #22c55e; }
    .role-slu      { background: rgba(251,146,60,0.15); color: #fb923c; }
    .role-campo    { background: rgba(20,184,166,0.15); color: #14b8a6; }
    /* Regras de negocio */
    .regras-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
    .regras-table th {
        background: var(--bg-secondary);
        font-weight: var(--font-semibold);
        font-size: var(--text-xs);
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: var(--space-2) var(--space-3);
        border-bottom: 2px solid var(--border-color);
        text-align: left;
    }
    .regras-table td {
        padding: var(--space-2) var(--space-3);
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .regras-table tr:last-child td { border-bottom: none; }
    .tag-admin { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .tag-dono  { background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .tag-all   { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .cond-badge { background: var(--bg-tertiary); color: var(--text-muted); padding: 1px 8px; border-radius: 999px; font-size: 11px; border: 1px solid var(--border-color); }
    /* Totais */
    .total-cell { font-size: var(--text-xs); color: var(--text-muted); text-align: center; padding-top: var(--space-1) !important; }
</style>
@endpush

@section('header')
    <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Matriz de Permissoes</span>
    <div style="width: 40px;"></div>
@endsection

@section('content')
<div class="page-content">

    {{-- Cabecalho --}}
    <div class="card mb-4">
        <div class="card-body" style="display:flex; align-items:center; gap:var(--space-3);">
            <div style="flex:1;">
                <h2 style="font-size:var(--text-base); font-weight:var(--font-semibold); margin:0 0 4px;">
                    Matriz de Roles &amp; Permissoes
                </h2>
                <p class="text-muted" style="font-size:var(--text-xs); margin:0;">
                    {{ $roles->count() }} roles &middot; {{ collect($grupos)->flatten()->count() }} permissoes &middot; gerado em {{ now()->format('d/m/Y H:i') }}
                </p>
            </div>
            <div style="display:flex; gap:var(--space-2);">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary btn-sm">Roles</a>
                <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary btn-sm">Permissoes</a>
            </div>
        </div>
    </div>

    {{-- Matriz --}}
    <div class="matriz-wrapper">
        <table class="matriz-table">
            <thead>
                <tr>
                    <th class="th-corner">
                        <span style="font-size:var(--text-xs); color:var(--text-muted);">Permissao</span>
                    </th>
                    @foreach($roles as $role)
                        @php
                            $badgeClass = match($role->name) {
                                'admin'              => 'role-admin',
                                'supervisor'         => 'role-sup',
                                'coordenador'        => 'role-coord',
                                'guardas-municipais' => 'role-guarda',
                                'agentes-slu'        => 'role-slu',
                                'agentes-campo'      => 'role-campo',
                                default              => 'role-campo',
                            };
                            $displayName = \App\Support\RoleDisplay::label($role->name);
                            $totalRole = count($matrix[$role->name] ?? []);
                        @endphp
                        <th class="th-role">
                            {{ $displayName }}<br>
                            <span class="role-badge {{ $badgeClass }}">{{ $totalRole }} perms</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($grupos as $grupo => $permissoes)
                    <tr class="tr-grupo">
                        <td colspan="{{ $roles->count() + 1 }}">{{ $grupo }}</td>
                    </tr>
                    @foreach($permissoes as $permissao)
                        <tr>
                            <td class="td-permissao">{{ $permissao }}</td>
                            @foreach($roles as $role)
                                <td class="td-check">
                                    @if(isset($matrix[$role->name][$permissao]))
                                        <span class="check-yes" title="Concedida">&#10003;</span>
                                    @else
                                        <span class="check-no" title="Nao concedida">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                {{-- Linha de totais --}}
                <tr>
                    <td class="total-cell" style="text-align:left; padding-left:var(--space-4);">
                        <strong>Total de permissoes</strong>
                    </td>
                    @foreach($roles as $role)
                        <td class="total-cell">
                            <strong>{{ count($matrix[$role->name] ?? []) }}</strong>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Legenda --}}
    <div class="card mb-4">
        <div class="card-body" style="display:flex; gap:var(--space-6); flex-wrap:wrap; font-size:var(--text-xs); color:var(--text-muted);">
            <span><span class="check-yes">&#10003;</span> Permissao concedida ao role via Spatie</span>
            <span><span class="check-no">&mdash;</span> Sem permissao</span>
            <span style="color:var(--text-muted);">* Permissoes Spatie controlam acesso a recursos. Regras de negocio de vistoria estao abaixo.</span>
        </div>
    </div>

    {{-- Regras de negocio da VistoriaPolicy --}}
    <div class="card mb-4">
        <div class="card-header" style="font-weight:var(--font-semibold); font-size:var(--text-sm);">
            Regras de Negocio — VistoriaPolicy
        </div>
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="regras-table">
                    <thead>
                        <tr>
                            <th>Acao</th>
                            <th>Estado da Vistoria</th>
                            <th>Quem pode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($regrasNegocio as $regra)
                            <tr>
                                <td style="font-weight:var(--font-medium);">{{ $regra['acao'] }}</td>
                                <td><span class="cond-badge">{{ $regra['condicao'] }}</span></td>
                                <td>
                                    @if($regra['admin'])
                                        <span class="tag-admin">Admin</span>
                                    @elseif(str_contains($regra['regra'], 'Qualquer'))
                                        <span class="tag-all">Qualquer usuario</span>
                                    @else
                                        <span class="tag-dono">Dono da vistoria</span>
                                    @endif
                                    <span style="font-size:var(--text-xs); color:var(--text-muted); margin-left:6px;">{{ $regra['regra'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Usuarios teste --}}
    <div class="card mb-4">
        <div class="card-header" style="font-weight:var(--font-semibold); font-size:var(--text-sm);">
            Usuarios Teste
        </div>
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="regras-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Senha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $usuariosTeste = [
                                ['admin@teste.local',       'admin',               'role-admin'],
                                ['supervisor@teste.local',  'Supervisor',          'role-sup'],
                                ['coordenador@teste.local', 'Coordenador',         'role-coord'],
                                ['guarda@teste.local',      'Guardas Municipais',  'role-guarda'],
                                ['agente.slu@teste.local',  'Agentes da SLU',      'role-slu'],
                                ['agente.campo@teste.local','Agentes de Campo',    'role-campo'],
                                ['sem.role@teste.local',    '—',                   ''],
                            ];
                        @endphp
                        @foreach($usuariosTeste as [$email, $role, $badge])
                            <tr>
                                <td style="font-family:monospace; font-size:var(--text-xs);">{{ $email }}</td>
                                <td>
                                    @if($badge)
                                        <span class="role-badge {{ $badge }}" style="margin:0;">{{ $role }}</span>
                                    @else
                                        <span class="text-muted" style="font-size:var(--text-xs);">sem role</span>
                                    @endif
                                </td>
                                <td style="font-family:monospace; font-size:var(--text-xs); color:var(--text-muted);">Cras@2026</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
