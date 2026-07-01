@if (config('app.homologacao_banner'))
    <div class="homologacao-banner" role="status" aria-live="polite">
        <p class="homologacao-banner-text">
            <strong>Sistema em Homologação.</strong>
            Os dados lançados são somente para teste.
        </p>
    </div>
@endif
