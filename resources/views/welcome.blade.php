<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        SIZEM v1
    </h2>

    <p class="text-muted text-center mb-6" style="font-size: var(--text-sm);">
        Sistema Integrado de Zeladoria Municipal — gestao de vistorias e monitoramento urbano em Belo Horizonte.
    </p>

    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg btn-block">Acessar o sistema</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg btn-block">Entrar</a>
        @endauth
    </div>
</x-guest-layout>
