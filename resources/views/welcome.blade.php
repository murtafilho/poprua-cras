<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        POPRUA v2
    </h2>

    <p class="text-muted text-center mb-6" style="font-size: var(--text-sm);">
        Sistema de gestao de vistorias e monitoramento de populacao em situacao de rua em Belo Horizonte.
    </p>

    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg btn-block">Acessar o sistema</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg btn-block">Entrar</a>
        @endauth
    </div>
</x-guest-layout>
