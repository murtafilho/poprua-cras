<section>
    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        Atualize as informacoes do seu perfil e endereco de e-mail.
    </p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="form-group">
            <label for="name" class="form-label">Nome</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-input"
                value="{{ old('name', $user->name) }}"
                required
                autofocus
                autocomplete="name"
            >
            @error('name')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="email" class="form-label">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-input"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            >
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="alert alert-warning mt-3">
                    <p style="font-size: var(--text-sm);">
                        Seu endereco de e-mail nao foi verificado.
                        <button form="send-verification" class="link" style="text-decoration: underline;">
                            Clique aqui para reenviar o e-mail de verificacao.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="text-success mt-2" style="font-size: var(--text-sm);">
                            Um novo link de verificacao foi enviado para seu e-mail.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="form-actions" style="position: relative; padding: 0; background: none; border: none;">
            <button type="submit" class="btn btn-primary">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Salvar Alteracoes
            </button>
        </div>
    </form>
</section>
