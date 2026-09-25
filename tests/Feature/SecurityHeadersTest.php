<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleForcedBrowsing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $this->assertStringContainsString(
            "object-src 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $response
            ->assertHeader('X-XSS-Protection', '0')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
    }

    public function test_csp_blocks_inline_scripts_that_lack_the_request_nonce(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-([A-Za-z0-9]+)'/", $policy);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $matches);
        $nonce = $matches[1];

        $scriptSource = collect(explode(';', $policy))
            ->map(fn (string $directive): string => trim($directive))
            ->first(fn (string $directive): bool => str_starts_with($directive, 'script-src '));
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSource);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);

        $html = $response->getContent();
        preg_match_all('/<script\b[^>]*>/i', $html, $scriptTags);
        $this->assertNotEmpty($scriptTags[0]);
        foreach ($scriptTags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag);
        }

        $nextPolicy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString("'nonce-{$nonce}'", $nextPolicy);
    }

    public function test_views_do_not_use_inline_event_handler_attributes(): void
    {
        $views = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'));

        foreach ($views as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/<[a-z][^>]*\son[a-z]+\s*=\s*["\']/i',
                $view->getContents(),
                "{$view->getRelativePathname()} uses an inline event handler that the CSP blocks.",
            );
        }
    }

    public function test_repeated_forbidden_or_missing_url_probes_are_locked_out(): void
    {
        for ($attempt = 0; $attempt < ThrottleForcedBrowsing::MAX_FAILURES; $attempt++) {
            $this->get("/admin-probe-{$attempt}")->assertNotFound();
        }

        $this->get('/')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_normal_browsing_and_missing_menu_images_do_not_trigger_the_lockout(): void
    {
        for ($attempt = 0; $attempt < ThrottleForcedBrowsing::MAX_FAILURES + 5; $attempt++) {
            $this->get('/');
            $this->get("/media/products/missing-{$attempt}.jpg")->assertNotFound();
        }

        $this->get('/')->assertOk();
    }

    public function test_customers_forcing_admin_urls_are_eventually_locked_out(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        for ($attempt = 0; $attempt < ThrottleForcedBrowsing::MAX_FAILURES; $attempt++) {
            $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
        }

        $this->actingAs($customer)->get(route('shop'))->assertStatus(429);
    }

    public function test_hsts_is_sent_only_for_secure_production_requests(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('https://localhost/')
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
    }
}
