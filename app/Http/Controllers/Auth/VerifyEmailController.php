<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            // Disparar o evento Verified mesmo que o User não implemente MustVerifyEmail
            // O Laravel permite isso e o evento será útil para listeners
            event(new Verified($user)); // @phpstan-ignore argument.type
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
