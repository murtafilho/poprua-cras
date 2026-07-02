@extends('layouts.app')

@section('title', 'Sobre')

@section('header')
    <h1 class="page-title">Sobre</h1>
@endsection

@section('content')
    <div class="page-content">
        <div class="container" style="max-width: 42rem;">
            <div class="card mb-4">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <x-application-logo style="width: 48px; height: 48px; color: var(--accent-primary); flex-shrink: 0;" />
                        <div>
                            <h2 style="font-size: var(--text-lg); font-weight: var(--font-bold); margin: 0;">
                                {{ config('app.brand', 'SIZEM') }}
                            </h2>
                            <p class="text-muted" style="font-size: var(--text-sm); margin-top: var(--space-1);">
                                {{ config('app.name') }}
                            </p>
                        </div>
                    </div>

                    <p class="text-secondary" style="font-size: var(--text-sm); line-height: 1.6;">
                        Sistema da Prefeitura de Belo Horizonte para registro e acompanhamento de zeladorias urbanas,
                        integrado às equipes de fiscalização e assistência social.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-4);">
                        Desenvolvimento e responsabilidade técnica
                    </h3>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Responsável técnico</span>
                            <span class="info-value">Roberto Murta</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Contato</span>
                            <span class="info-value">
                                <a href="mailto:rluciano@pbh.gov.br" style="color: var(--accent-primary); text-decoration: none;">
                                    rluciano@pbh.gov.br
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-4);">
                        Gerência de Informação da Fiscalização — GINFI
                    </h3>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Responsável pela Gerência</span>
                            <span class="info-value">Cássio Soares Martins</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Contato</span>
                            <span class="info-value">
                                <a href="mailto:ginfi@pbh.gov.br" style="color: var(--accent-primary); text-decoration: none;">
                                    ginfi@pbh.gov.br
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
