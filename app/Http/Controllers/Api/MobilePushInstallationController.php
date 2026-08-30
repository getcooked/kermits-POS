<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileApiToken;
use App\Models\MobilePushInstallation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MobilePushInstallationController extends Controller
{
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'provider' => ['sometimes', 'string', Rule::in(['fcm'])],
            'identifier_kind' => ['sometimes', 'string', Rule::in(['fid'])],
            'identifier' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', Rule::in(['android'])],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var MobileApiToken $mobileToken */
        $mobileToken = $request->attributes->get('mobile_token');
        $identifier = $validated['identifier'];
        $identifierHash = hash('sha256', $identifier);

        DB::transaction(function () use ($request, $mobileToken, $validated, $identifier, $identifierHash): void {
            $matchingIdentifier = MobilePushInstallation::query()
                ->where('identifier_hash', $identifierHash)
                ->lockForUpdate()
                ->first();
            $currentInstallation = MobilePushInstallation::query()
                ->where('mobile_api_token_id', $mobileToken->id)
                ->lockForUpdate()
                ->first();

            if ($currentInstallation && $currentInstallation->isNot($matchingIdentifier)) {
                $currentInstallation->delete();
            }

            ($matchingIdentifier ?? new MobilePushInstallation)->forceFill([
                'user_id' => $request->user()->id,
                'mobile_api_token_id' => $mobileToken->id,
                'provider' => $validated['provider'] ?? 'fcm',
                'identifier_kind' => $validated['identifier_kind'] ?? 'fid',
                'identifier' => $identifier,
                'identifier_hash' => $identifierHash,
                'platform' => $validated['platform'] ?? 'android',
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
            ])->save();
        });

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        /** @var MobileApiToken $mobileToken */
        $mobileToken = $request->attributes->get('mobile_token');
        $mobileToken->pushInstallation()->delete();

        return response()->noContent();
    }
}
