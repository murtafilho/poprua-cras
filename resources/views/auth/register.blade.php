<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Registrar') }}
    </h2>

    <form method="POST" action="{{ route('register') }}" style="display: flex; flex-direction: column; gap: var(--space-4);">
        @csrf

        <div class="form-group">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input
                id="name"
                class="form-input @error('name') border-danger @enderror"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
            />
            @error('name')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input
                id="email"
                class="form-input @error('email') border-danger @enderror"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                placeholder="seu@email.com"
            />
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input
                id="password"
                class="form-input @error('password') border-danger @enderror"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="********"
            />
            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input
                id="password_confirmation"
                class="form-input"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="********"
            />
        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                {{ __('Register') }}
            </button>

            <p class="text-center text-muted" style="font-size: var(--text-sm);">
                {{ __('Já tem conta?') }}
                <a href="{{ route('login') }}" style="font-weight: var(--font-medium);">
                    {{ __('Entrar') }}
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>
