@extends('layouts.app')

@section('title', 'Gerenciar Permissoes')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Permissoes</span>
        <a href="{{ route('admin.permissions.create') }}" class="btn btn-ghost btn-icon" title="Nova permissao">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </a>
    </div>
@endsection

@section('content')
    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-error mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body" style="padding: var(--space-3);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
                    <div class="admin-nav-links">
                        <a href="{{ route('admin.roles.index') }}" class="link">Roles</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('admin.users.index') }}" class="link">Usuarios</a>
                    </div>
                    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary btn-sm hide-mobile">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nova Permissao
                    </a>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="hide-mobile">Roles</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permissions as $permission)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $permission->name }}</span>
                                <div class="mobile-only text-muted mt-1" style="font-size: var(--text-xs);">
                                    @if($permission->roles->count() > 0)
                                        {{ $permission->roles->pluck('name')->join(', ') }}
                                    @else
                                        Sem roles
                                    @endif
                                </div>
                            </td>
                            <td class="hide-mobile text-muted">
                                @if($permission->roles->count() > 0)
                                    {{ $permission->roles->pluck('name')->join(', ') }}
                                @else
                                    <span>-</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta permissao?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span class="hide-mobile">Excluir</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted" style="padding: var(--space-8);">
                                Nenhuma permissao cadastrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
