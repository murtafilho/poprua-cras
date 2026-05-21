<section>
    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        Certifique-se de que sua conta esta usando uma senha longa e aleatoria para maior seguranca.
    </p>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="form-group">
            <label for="update_password_current_password" class="form-label">Senha Atual</label>
            <input
                type="password"
                id="update_password_current_password"
                name="current_password"
                class="form-input"
                autocomplete="current-password"
            >
            @error('current_password', 'updatePassword')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="update_password_password" class="form-label">Nova Senha</label>
            <input
                type="password"
                id="update_password_password"
                name="password"
                class="form-input"
                autocomplete="new-password"
            >
            @error('password', 'updatePassword')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="update_password_password_confirmation" class="form-label">Confirmar Nova Senha</label>
            <input
                type="password"
                id="update_password_password_confirmation"
                name="password_confirmation"
                class="form-input"
                autocomplete="new-password"
            >
            @error('password_confirmation', 'updatePassword')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-actions" style="position: relative; padding: 0; background: none; border: none;">
            <button type="submit" class="btn btn-primary">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Atualizar Senha
            </button>
        </div>
    </form>
</section>
