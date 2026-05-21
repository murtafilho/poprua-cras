@extends('layouts.app')

@section('title', 'Editar Membro')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.membros-equipe.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Editar Membro</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">
        <div class="card">
            <div class="card-body">
                <h2 class="form-section-title">Editar {{ $membro->nome }}</h2>

                <form action="{{ route('admin.membros-equipe.update', $membro) }}" method="POST">
                    @method('PUT')
                    @include('admin.membros-equipe._form')

                    <div class="form-actions" style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                        <a href="{{ route('admin.membros-equipe.index') }}" class="btn btn-ghost">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
