{{--
    Registro do Service Worker — um único lugar.

    Já houve três versões deste trecho, cada uma montando o caminho de um jeito:
    o layout app usava a meta app-base, o fullscreen usava asset(), e o
    offline-upload.js caía em '/sw.js' na raiz do domínio quando a meta não
    existia — 404 em produção, que roda em subdiretório, e a página inicial é
    justamente uma das que não têm a meta.

    asset() resolve no servidor e acerta em qualquer ambiente.
--}}
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '{{ asset('/') }}' })
            .then(function (reg) {
                // O app de campo fica aberto por horas; sem isto só pegaria
                // versão nova ao reabrir.
                setInterval(function () { reg.update(); }, 1800000);
                var recarregando = false;
                navigator.serviceWorker.addEventListener('controllerchange', function () {
                    if (!recarregando) { recarregando = true; window.location.reload(); }
                });
            })
            .catch(function (erro) {
                console.error('[SIZEM] falha ao registrar o service worker:', erro);
            });
    }
</script>
