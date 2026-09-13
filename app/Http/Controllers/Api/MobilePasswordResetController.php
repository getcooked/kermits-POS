<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MobilePasswordResetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
            ->where('role', User::ROLE_CUSTOMER)
            ->first();

        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (TransportExceptionInterface) {
                // The broker creates the token before sending. A failed send must
                // not throttle a retry for a link the customer never received.
                Password::deleteToken($user);
                Log::warning('Mobile password reset email delivery failed.');

                return response()->json(['message' => 'The password reset email could not be sent. Please try again later.'], 503);
            }
        }

        return response()->json([
            'message' => 'If an active customer account uses that email address, check your inbox and spam folder for a password reset link. If you recently requested one, use the latest email or wait a minute before trying again.',
        ]);
    }
}
