<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\MobileRecaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MobilePasswordResetController extends Controller
{
    private const RESET_LINK_MESSAGE = 'If an eligible account exists, a password reset link has been sent.';

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'recaptcha_token' => MobileRecaptcha::rules($request),
        ], MobileRecaptcha::messages());

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
            ->where('role', User::ROLE_CUSTOMER)
            ->first();

        if (! $user) {
            return response()->json(['message' => self::RESET_LINK_MESSAGE]);
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (TransportExceptionInterface) {
            // The broker creates the token before sending. A failed send must
            // not throttle a retry for a link the customer never received.
            Password::deleteToken($user);
            Log::warning('Mobile password reset email delivery failed.');

            return response()->json(['message' => self::RESET_LINK_MESSAGE]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            Log::notice('Mobile password reset link was not sent.', ['status' => $status]);
        }

        return response()->json(['message' => self::RESET_LINK_MESSAGE]);
    }
}
