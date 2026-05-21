@extends('layouts.app')

@section('title', 'Gerenciar Usuarios')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Gerenciar Usuarios</span>
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
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary" style="margin-bottom: var(--space-2);">
                        <svg style="width: 16px; height: 16px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Cadastrar Usuário
                    </a>
                </div>
                <div class="admin-nav-links">
                        <a href="{{ route('admin.roles.index') }}" class="link">Roles</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('admin.permissions.index') }}" class="link">Permissions</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="hide-mobile">Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $user->name }}</span>
                                <div class="mobile-only text-muted text-sm">{{ $user->email }}</div>
                            </td>
                            <td class="hide-mobile text-muted">{{ $user->email }}</td>
                            <td>
                                <form action="{{ route('admin.users.roles.update', $user) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" class="form-input form-select" style="min-width: 120px;" onchange="this.form.submit()">
                                        <option value="">Sem role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                                                {{ \App\Support\RoleDisplay::label($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted" style="padding: var(--space-8);">
                                Nenhum usuario cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
