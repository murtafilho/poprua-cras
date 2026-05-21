@extends('layouts.app')

@section('title', 'Cadastrar Usuário')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Cadastrar Usuário</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content" style="max-width: 480px; margin: 0 auto;">

        @if($errors->any())
            <div class="alert alert-error mb-4">
                <ul style="margin: 0; padding-left: var(--space-4);">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body" style="padding: var(--space-4);">
                <form action="{{ route('admin.users.store') }}" method="POST" autocomplete="off">
                    @csrf

                    <div class="form-group mb-4">
                        <label class="form-label" for="name">Nome</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-input @error('name') is-invalid @enderror"
                            value="{{ old('name') }}"
                            required
                            autofocus
                        >
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label" for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            required
                        >
                        @error('email')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label" for="password">Senha</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input @error('password') is-invalid @enderror"
                            required
                        >
                        @error('password')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label" for="password_confirmation">Confirmar Senha</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-input"
                            required
                        >
                    </div>

                    <div class="form-group mb-5">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-input form-select">
                            <option value="">Sem role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                                    {{ \App\Support\RoleDisplay::label($role->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex; gap: var(--space-3);">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="flex: 1;">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            Cadastrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
