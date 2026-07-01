<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Entrar') }}
    </h2>

    {{-- Session Status --}}
    @if (session('status'))
        <div class="alert alert-success mb-4">
            <div class="alert-content">
                <p class="alert-message">{{ __(session('status')) }}</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" style="display: flex; flex-direction: column; gap: var(--space-4);">
        @csrf

        {{-- Email Address --}}
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

        {{-- Password --}}
        <div class="form-group">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input
                id="password"
                class="form-input @error('password') border-danger @enderror"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="********"
            />
            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me --}}
        <label class="form-check">
            <input
                id="remember_me"
                type="checkbox"
                class="form-check-input"
                name="remember"
            >
            <span class="form-check-label">{{ __('Remember me') }}</span>
        </label>

        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="font-size: var(--text-sm);">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
</x-guest-layout>
