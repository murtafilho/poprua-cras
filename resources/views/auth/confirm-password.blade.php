<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Confirmar Senha') }}
    </h2>

    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" style="display: flex; flex-direction: column; gap: var(--space-4);">
        @csrf

        <div class="form-group">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input
                id="password"
                class="form-input @error('password') border-danger @enderror"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('Confirm') }}
        </button>
    </form>
</x-guest-layout>
