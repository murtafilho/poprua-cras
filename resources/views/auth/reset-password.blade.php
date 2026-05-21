<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Redefinir Senha') }}
    </h2>

    <form method="POST" action="{{ route('password.store') }}" style="display: flex; flex-direction: column; gap: var(--space-4);">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="form-group">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input
                id="email"
                class="form-input @error('email') border-danger @enderror"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
                autocomplete="username"
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
            />
            @error('password_confirmation')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('Reset Password') }}
        </button>
    </form>
</x-guest-layout>
