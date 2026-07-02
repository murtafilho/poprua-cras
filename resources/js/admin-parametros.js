function initParametrosAdmin() {
    document.querySelectorAll('.param-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.param-tab').forEach((t) => t.classList.remove('active'));
            document.querySelectorAll('.param-panel').forEach((p) => p.classList.remove('active'));
            tab.classList.add('active');
            const panel = document.getElementById(tab.dataset.target);
            if (panel) {
                panel.classList.add('active');
            }
        });
    });

    document.querySelectorAll('[data-delete-url]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const chave = btn.dataset.deleteChave;
            const confirmacao = prompt(`Digite "REMOVER" para confirmar a exclusão do parâmetro: ${chave}`);
            if (confirmacao !== 'REMOVER') {
                return;
            }

            const form = document.getElementById('form-delete-param');
            if (!form) {
                return;
            }

            form.action = btn.dataset.deleteUrl;
            form.submit();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initParametrosAdmin);
} else {
    initParametrosAdmin();
}
