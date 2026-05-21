@extends('layouts.app')

@section('title', 'Membros da Equipe')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Membros da Equipe</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
                <h2 style="margin: 0;">Membros da Equipe</h2>
                <a href="{{ route('admin.membros-equipe.create') }}" class="btn btn-primary">
                    <svg style="width: 16px; height: 16px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Cadastrar Membro
                </a>
            </div>
        </div>

        @forelse($equipes as $key => $label)
            @if(($membrosPorEquipe[$key] ?? collect())->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">{{ $label }}</h3>
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Matricula</th>
                                    <th>E-mail</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($membrosPorEquipe[$key] as $m)
                                    <tr>
                                        <td>{{ $m->nome }}</td>
                                        <td>{{ $m->matricula ?: '—' }}</td>
                                        <td>{{ $m->email ?: '—' }}</td>
                                        <td>
                                            @if($m->ativo)
                                                <span class="badge badge-success">Ativo</span>
                                            @else
                                                <span class="badge badge-secondary">Inativo</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="{{ route('admin.membros-equipe.edit', $m) }}" class="btn btn-sm btn-ghost">Editar</a>
                                            <form action="{{ route('admin.membros-equipe.destroy', $m) }}" method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Remover {{ $m->nome }}? Esta acao apaga tambem as participacoes em vistorias.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-ghost text-danger">Remover</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @empty
        @endforelse

        @if($membrosPorEquipe->isEmpty())
            <div class="card">
                <div class="card-body text-center">
                    <p>Nenhum membro cadastrado. Comece <a href="{{ route('admin.membros-equipe.create') }}">cadastrando o primeiro membro</a>.</p>
                </div>
            </div>
        @endif
    </div>
@endsection
