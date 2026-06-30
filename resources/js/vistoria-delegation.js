/** Delegação de cliques em formulários de vistoria (create/edit). */
export function initDynamicClickHandlers(handlers) {
    const {
        goToStep,
        removerFoto,
        abrirModalMorador,
        removerMorador,
        desvincularPessoa,
        vincularPessoa,
    } = handlers;

    document.getElementById('review-checklist')?.addEventListener('click', (e) => {
        const badge = e.target.closest('[data-go-step]');
        if (badge) {
            goToStep(Number(badge.dataset.goStep));
        }
    });

    document.getElementById('fotos-preview')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-foto-index]');
        if (btn) {
            removerFoto(Number(btn.dataset.fotoIndex));
        }
    });

    document.getElementById('novos-moradores')?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-edit-morador]');
        if (editBtn) {
            abrirModalMorador(Number(editBtn.dataset.editMorador));
            return;
        }
        const removeBtn = e.target.closest('[data-remove-morador]');
        if (removeBtn) {
            removerMorador(Number(removeBtn.dataset.removeMorador));
        }
    });

    document.getElementById('pessoas-vinculadas')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-desvincular-pessoa]');
        if (btn) {
            desvincularPessoa(Number(btn.dataset.desvincularPessoa));
        }
    });

    document.getElementById('busca-pessoa-resultados')?.addEventListener('click', (e) => {
        const item = e.target.closest('[data-vincular-pessoa]');
        if (!item) {
            return;
        }
        try {
            const p = JSON.parse(item.dataset.vincularPessoa);
            vincularPessoa(p.id, p.nome, p.apelido, p.pontoOrigem);
        } catch (err) {
            console.error('Dados inválidos de pessoa:', err);
        }
    });
}

export function initStepperNavigation({ goToStep, getCurrentTab, totalTabs, withPrevNext = false }) {
    document.getElementById('progress-stepper')?.addEventListener('click', (e) => {
        const item = e.target.closest('.stepper-item[data-step]');
        if (item) {
            goToStep(Number(item.dataset.step));
        }
    });

    if (!withPrevNext) {
        return;
    }

    document.getElementById('btn-prev')?.addEventListener('click', () => {
        goToStep(Math.max(0, getCurrentTab() - 1));
    });
    document.getElementById('btn-next')?.addEventListener('click', () => {
        goToStep(Math.min(totalTabs - 1, getCurrentTab() + 1));
    });
}
