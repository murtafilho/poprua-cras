<section>
    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        Depois que sua conta for excluida, todos os seus recursos e dados serao permanentemente removidos.
        Antes de excluir sua conta, baixe todos os dados ou informacoes que deseja manter.
    </p>

    <button
        type="button"
        class="btn btn-danger"
        onclick="document.getElementById('delete-account-modal').classList.remove('hidden')"
    >
        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        Excluir Minha Conta
    </button>

    <!-- Modal de Confirmacao -->
    <div id="delete-account-modal" class="modal-overlay hidden">
        <div class="modal-container">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-header">
                    <h3 class="modal-title" style="color: var(--color-danger);">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Confirmar Exclusao
                    </h3>
                    <button
                        type="button"
                        class="btn btn-ghost btn-icon"
                        onclick="document.getElementById('delete-account-modal').classList.add('hidden')"
                    >
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
                        Tem certeza de que deseja excluir sua conta? Todos os seus dados serao permanentemente removidos.
                        Digite sua senha para confirmar.
                    </p>

                    <div class="form-group">
                        <label for="password" class="form-label">Senha</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Digite sua senha"
                            required
                        >
                        @error('password', 'userDeletion')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        onclick="document.getElementById('delete-account-modal').classList.add('hidden')"
                    >
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Sim, Excluir Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

@if($errors->userDeletion->isNotEmpty())
<script>
    document.getElementById('delete-account-modal').classList.remove('hidden');
</script>
@endif
