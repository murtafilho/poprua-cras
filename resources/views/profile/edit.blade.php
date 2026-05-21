@extends('layouts.app')

@section('title', 'Perfil')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Meu Perfil</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">
        @if(session('status') === 'profile-updated')
            <div class="alert alert-success mb-4">
                Perfil atualizado com sucesso.
            </div>
        @endif

        @if(session('status') === 'password-updated')
            <div class="alert alert-success mb-4">
                Senha atualizada com sucesso.
            </div>
        @endif

        <!-- Avatar e Info do Usuario -->
        <div class="card mb-4">
            <div class="card-body" style="display: flex; align-items: center; gap: var(--space-4);">
                <div class="user-avatar-lg">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <div>
                    <h2 style="font-size: var(--text-lg); font-weight: var(--font-semibold); color: var(--text-primary);">
                        {{ $user->name }}
                    </h2>
                    <p class="text-muted" style="font-size: var(--text-sm);">{{ $user->email }}</p>
                    @if($user->roles->count() > 0)
                        <span class="badge badge-primary" style="margin-top: var(--space-2);">
                            {{ $user->roles->first()->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Informacoes do Perfil -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Informacoes do Perfil
                </h3>
            </div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <!-- Atualizar Senha -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Atualizar Senha
                </h3>
            </div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <!-- Excluir Conta -->
        <div class="card mb-4" style="border-color: var(--color-danger-dim);">
            <div class="card-header" style="background: var(--color-danger-dim);">
                <h3 class="card-title" style="color: var(--color-danger);">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Zona de Perigo
                </h3>
            </div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
@endsection
