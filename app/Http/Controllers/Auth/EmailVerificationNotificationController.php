<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Reenvío del correo de verificación de cuenta.
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * Reenvía el correo para verificar la cuenta.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $e) {
            report($e);

            return back()->with('status', 'verification-failed');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
