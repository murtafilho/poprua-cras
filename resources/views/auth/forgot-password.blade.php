<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Esqueci minha senha') }}
    </h2>

    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    @if (session('status'))
        <div class="alert alert-success mb-4">
            <div class="alert-content">
                <p class="alert-message">{{ __(session('status')) }}</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" style="display: flex; flex-direction: column; gap: var(--space-4);">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input
                id="email"
                class="form-input @error('email') border-danger @enderror"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="seu@email.com"
            />
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('Email Password Reset Link') }}
        </button>

        <p class="text-center" style="font-size: var(--text-sm);">
            <a href="{{ route('login') }}">{{ __('Voltar ao login') }}</a>
        </p>
    </form>
</x-guest-layout>
