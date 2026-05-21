<x-guest-layout>
    <h2 class="text-center mb-6" style="font-size: var(--text-xl); font-weight: var(--font-semibold);">
        {{ __('Verificar Email') }}
    </h2>

    <p class="text-muted mb-4" style="font-size: var(--text-sm);">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success mb-4">
            <div class="alert-content">
                <p class="alert-message">{{ __('A new verification link has been sent to the email address you provided during registration.') }}</p>
            </div>
        </div>
    @endif

    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                {{ __('Resend Verification Email') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-block" style="font-size: var(--text-sm);">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
