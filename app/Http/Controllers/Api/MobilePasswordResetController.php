<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json([
            'message' => 'If an active customer account uses that email address, a password reset link has been sent.',
        ]);
    }
}
