@extends('layouts.app')

@section('title', 'Gerenciar Roles')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Gerenciar Roles</span>
        <div style="width: 44px;"></div>
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
                        <a href="{{ route('admin.permissions.index') }}" class="link">Permissions</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('admin.users.index') }}" class="link">Usuarios</a>
                    </div>
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nova Role
                    </a>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="hide-mobile">Descricao</th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-center hide-mobile">Permissions</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>
                                <span class="font-medium">{{ \App\Support\RoleDisplay::label($role->name) }}</span>
                                <code class="text-xs text-muted" style="margin-left:.5rem;">{{ $role->name }}</code>
                            </td>
                            <td class="hide-mobile text-muted">
                                {{ $role->description ?? '-' }}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-secondary">{{ $role->users_count }}</span>
                            </td>
                            <td class="text-center hide-mobile">
                                <span class="badge badge-info">{{ $role->permissions->count() }}</span>
                            </td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-ghost btn-sm">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span class="hide-mobile">Editar</span>
                                    </a>
                                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" style="display: inline;" x-on:submit="if (!confirm('Tem certeza que deseja excluir esta role?')) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm text-danger">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span class="hide-mobile">Excluir</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: var(--space-8);">
                                Nenhuma role cadastrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
