@extends('layouts.app')

@section('title', 'Minha Equipe')

@section('header')
    <a href="{{ url()->previous() }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Minha Equipe</span>
    <div style="width: 38px;"></div>
@endsection

@push('styles')
<style>
    .equipe-intro {
        margin-bottom: var(--space-4);
        color: var(--text-secondary);
        font-size: var(--text-sm);
        line-height: 1.5;
    }
    .equipe-membros {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .equipe-membro {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3);
        border-radius: 6px;
        cursor: pointer;
        transition: background 80ms;
    }
    .equipe-membro:hover { background: var(--bg-elevated, #2d3139); }
    .equipe-membro .checkbox {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        border: 2px solid var(--border-secondary, #353940);
        border-radius: 4px;
        position: relative;
        background: transparent;
        transition: all 120ms;
    }
    .equipe-membro input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .equipe-membro input[type="checkbox"]:checked + .checkbox {
        background: var(--accent-primary, #1B2A6B);
        border-color: var(--accent-primary, #1B2A6B);
    }
    .equipe-membro input[type="checkbox"]:checked + .checkbox::after {
        content: '';
        position: absolute;
        left: 5px;
        top: 1px;
        width: 6px;
        height: 11px;
        border: solid var(--text-inverse, #111214);
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .equipe-membro .info { flex: 1; min-width: 0; }
    .equipe-membro .nome {
        font-weight: 500;
        color: var(--text-primary);
        font-size: var(--text-base);
    }
    .equipe-membro .email {
        font-size: var(--text-xs);
        color: var(--text-secondary);
        margin-top: 1px;
    }
    .equipe-actions {
        margin-top: var(--space-4);
        display: flex;
        gap: var(--space-2);
        align-items: center;
        flex-wrap: wrap;
    }
    .equipe-count {
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }
    .equipe-count strong { color: var(--text-primary); }
</style>
@endpush

@section('content')
<div class="page-content">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Selecione os membros da sua equipe</span>
        </div>
        <div class="card-body">
            <p class="equipe-intro">
                Marque os colegas que normalmente trabalham com você em campo.
                Eles aparecerão pré-selecionados como participantes ao criar uma nova vistoria.
            </p>

            @if(session('success'))
                <div class="alert alert-success" style="margin-bottom: var(--space-3);">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('minha-equipe.update') }}">
                @csrf
                @method('PUT')

                <div class="equipe-membros">
                    @include('partials.equipe-checklist', [
                        'usuarios' => $usuarios,
                        'marcados' => $marcados,
                    ])
                </div>

                <div class="equipe-actions">
                    <button type="submit" class="btn btn-primary">Salvar equipe</button>
                    <span class="equipe-count">
                        <strong id="equipe-marcados-count">{{ count($marcados) }}</strong>
                        de {{ $usuarios->count() }} selecionados
                    </span>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const checks = document.querySelectorAll('.equipe-membro input[type="checkbox"]');
        const counter = document.getElementById('equipe-marcados-count');
        const updateCount = () => {
            counter.textContent = document.querySelectorAll('.equipe-membro input[type="checkbox"]:checked').length;
        };
        checks.forEach(c => c.addEventListener('change', updateCount));
    });
</script>
@endsection
