@extends('layouts.app')

@section('title', 'Nova Role')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Nova Role</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">
        <div style="max-width: 600px; margin: 0 auto;">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.roles.store') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label for="name" class="form-label required">Nome da Role</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
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
                                value="{{ old('description') }}"
                                placeholder="Ex: Acesso total ao sistema"
                                class="form-input @error('description') is-invalid @enderror"
                            >
                            @error('description')
                                <div class="form-errors">
                                    <span class="form-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>

                        <div class="form-actions" style="position: static; padding: var(--space-4) 0 0 0; background: transparent; border: none;">
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Criar Role
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
