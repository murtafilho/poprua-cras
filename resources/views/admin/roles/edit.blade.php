@extends('layouts.app')

@section('title', 'Editar Role')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Editar Role</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">
        <div style="max-width: 600px; margin: 0 auto;">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.roles.update', $role) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="name" class="form-label required">Nome da Role</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name', $role->name) }}"
                                placeholder="Ex: admin, editor, viewer"
                                class="form-input @error('name') is-invalid @enderror"
                                required
                            >
                            @error('name')
                                <div class="form-errors">
                                    <span class="form-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Descricao</label>
                            <input
                                type="text"
                                id="description"
                                name="description"
                                value="{{ old('description', $role->description) }}"
                                placeholder="Ex: Acesso total ao sistema"
                                class="form-input @error('description') is-invalid @enderror"
                            >
                            @error('description')
                                <div class="form-errors">
                                    <span class="form-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Permissions</label>
                            <div class="permissions-grid">
                                @foreach($permissions as $permission)
                                    <label class="checkbox-card">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->id }}"
                                            {{ in_array($permission->id, old('permissions', $rolePermissions)) ? 'checked' : '' }}
                                            class="form-checkbox"
                                        >
                                        <span>{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if($permissions->isEmpty())
                                <p class="text-muted mt-2" style="font-size: var(--text-sm);">Nenhuma permission cadastrada.</p>
                            @endif
                        </div>

                        <div class="form-actions" style="position: static; padding: var(--space-4) 0 0 0; background: transparent; border: none;">
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Salvar Alteracoes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
