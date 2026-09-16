<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReverseGeocoder
{
    public function resolve(float $latitude, float $longitude): ?string
    {
        $cacheKey = sprintf('reverse-geocode:%.5f:%.5f', $latitude, $longitude);
        $cachedAddress = Cache::get($cacheKey);

        if (is_string($cachedAddress) && $cachedAddress !== '') {
            return $cachedAddress;
        }

        $address = Cache::lock('reverse-geocoder-request', 10)->block(3, function () use ($latitude, $longitude): ?string {
            $lastRequestAt = (float) Cache::get('reverse-geocoder-last-request-at', 0);
            $waitMicroseconds = (int) max(0, (1 - (microtime(true) - $lastRequestAt)) * 1_000_000);

            if ($waitMicroseconds > 0) {
                usleep($waitMicroseconds);
            }

            try {
                $response = $this->client()->get('/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => 'en',
                ]);
            } finally {
                Cache::put('reverse-geocoder-last-request-at', microtime(true), now()->addMinute());
            }

            if (! $response->successful()) {
                return null;
            }

            return $this->formatAddress($response->json());
        });

        if ($address) {
            Cache::put($cacheKey, $address, now()->addDays(30));
        }

        return $address;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.reverse_geocoding.url'), '/'))
            ->acceptJson()
            ->withUserAgent((string) config('services.reverse_geocoding.user_agent'))
            ->timeout(8);
    }

    /** @param array<string, mixed> $result */
    private function formatAddress(array $result): ?string
    {
        $parts = Arr::get($result, 'address', []);

        if (! is_array($parts)) {
            return null;
        }

        $street = collect(['house_number', 'road'])
            ->map(fn (string $key): mixed => $parts[$key] ?? null)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->implode(' ');

        $namedParts = collect([
            $street,
            $parts['neighbourhood'] ?? null,
            $parts['quarter'] ?? null,
            $parts['suburb'] ?? null,
            $parts['village'] ?? null,
            $parts['hamlet'] ?? null,
            $parts['municipality'] ?? null,
            $parts['town'] ?? null,
            $parts['city'] ?? null,
            $parts['county'] ?? null,
            $parts['province'] ?? null,
            $parts['state'] ?? null,
            $parts['country'] ?? null,
        ])->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->values();

        return $namedParts->isNotEmpty() ? $namedParts->implode(', ') : null;
    }
}
