<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReverseGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_coordinates_are_resolved_to_a_named_address(): void
    {
        Http::fake([
            '*' => Http::response([
                'address' => [
                    'village' => 'Binaobao',
                    'municipality' => 'Bantayan',
                    'province' => 'Cebu',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $this->getJson('/location/reverse?latitude=11.169512&longitude=123.718780')
            ->assertOk()
            ->assertExactJson([
                'address' => 'Binaobao, Bantayan, Cebu, Philippines',
            ]);

        Http::assertSent(fn ($request): bool => $request['format'] === 'jsonv2'
            && (int) $request['addressdetails'] === 1
            && str_contains($request->header('User-Agent')[0], 'KermitsPOS'));
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        Http::fake();

        $this->getJson('/location/reverse?latitude=100&longitude=123.7')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        Http::assertNothingSent();
    }

    public function test_missing_geocoding_result_asks_for_manual_address(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Unable to geocode'], 404)]);

        $this->getJson('/location/reverse?latitude=11.169512&longitude=123.718780')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A named address could not be found for this location. Please enter it manually.');
    }
}
