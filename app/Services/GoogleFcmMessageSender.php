<?php

namespace App\Services;

use App\Contracts\FcmMessageSender;
use App\Support\FcmSendResult;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleFcmMessageSender implements FcmMessageSender
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array<string, string>  $data
     */
    public function send(string $installationId, array $data): FcmSendResult
    {
        $projectId = trim((string) config('services.fcm.project_id'));
        $configuredCredentials = trim((string) config('services.fcm.credentials'));

        if ($projectId === '' || $configuredCredentials === '') {
            Log::notice('Firebase push delivery is disabled because FCM configuration is incomplete.');

            return FcmSendResult::Disabled;
        }

        $credentialsPath = $this->credentialsPath($configuredCredentials);
        $response = Http::acceptJson()
            ->withToken($this->accessToken($credentialsPath))
            ->timeout(15)
            ->post(
                'https://fcm.googleapis.com/v1/projects/'.rawurlencode($projectId).'/messages:send',
                [
                    'message' => [
                        'fid' => $installationId,
                        'data' => $data,
                        'android' => [
                            'priority' => 'high',
                            'ttl' => '86400s',
                        ],
                    ],
                ],
            );

        if ($response->successful()) {
            return FcmSendResult::Sent;
        }

        if ($this->isInvalidInstallation($response)) {
            return FcmSendResult::InvalidInstallation;
        }

        throw new RuntimeException('Firebase push delivery failed with HTTP '.$response->status().'.');
    }

    private function credentialsPath(string $configuredPath): string
    {
        $isAbsolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $configuredPath) === 1;
        $path = $isAbsolute ? $configuredPath : base_path($configuredPath);

        if (! is_file($path)) {
            throw new RuntimeException('The configured Firebase service-account file is unavailable.');
        }

        return $path;
    }

    private function accessToken(string $credentialsPath): string
    {
        $cacheKey = 'fcm.access-token.'.hash('sha256', $credentialsPath);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = new ServiceAccountCredentials(self::MESSAGING_SCOPE, $credentialsPath);
        $auth = $credentials->fetchAuthToken();
        $accessToken = $auth['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Firebase authentication did not return an access token.');
        }

        $expiresIn = max(60, min(3000, ((int) ($auth['expires_in'] ?? 3600)) - 60));
        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    private function isInvalidInstallation(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        $errorCode = collect($response->json('error.details', []))
            ->pluck('errorCode')
            ->first(fn ($value): bool => is_string($value));

        return $errorCode === 'UNREGISTERED';
    }
}
