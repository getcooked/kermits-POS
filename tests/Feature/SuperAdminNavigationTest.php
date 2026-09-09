<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const SIDEBAR_ROUTES = [
        'tables.index',
        'dashboard',
        'superadmin.security.edit',
        'cashier',
        'reports',
        'inventory.index',
        'reservations.index',
        'products.index',
        'admins.index',
        'cashiers.index',
        'customers.index',
        'activity-logs.index',
        'settings.payment.edit',
    ];

    public function test_super_admin_can_follow_every_sidebar_link_and_see_the_current_page(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin);

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $links = $this->sidebarLinks($dashboard->getContent());
        $expectedUrls = array_map(fn (string $name): string => route($name), self::SIDEBAR_ROUTES);

        $this->assertEqualsCanonicalizing($expectedUrls, array_column($links, 'href'));

        foreach ($links as $link) {
            $response = $this->get($link['href'])->assertOk();
            $pageLinks = $this->sidebarLinks($response->getContent());
            $activeLinks = array_values(array_filter($pageLinks, fn (array $item): bool => $item['active']));
            $stylePlacement = $this->stylePlacement($response->getContent());

            $this->assertEqualsCanonicalizing($expectedUrls, array_column($pageLinks, 'href'));
            $this->assertCount(1, $activeLinks, 'Expected one current sidebar link at '.$link['href']);
            $this->assertSame($link['href'], $activeLinks[0]['href']);
            $this->assertGreaterThanOrEqual(2, $stylePlacement['head'], 'Expected global and page styles in <head> at '.$link['href']);
            $this->assertSame(0, $stylePlacement['body'], 'Page styles must not load after visible content at '.$link['href']);
            $this->assertAuthenticatedAs($superAdmin);
        }
    }

    public function test_guests_are_sent_to_login_from_every_super_admin_sidebar_destination(): void
    {
        foreach (self::SIDEBAR_ROUTES as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_customers_cannot_open_any_super_admin_sidebar_destination(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));

        foreach (self::SIDEBAR_ROUTES as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    private function sidebarLinks(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $anchors = $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " admin-sidebar ")]/nav/a');
        $links = [];

        foreach ($anchors as $anchor) {
            $links[] = [
                'href' => $anchor->getAttribute('href'),
                'active' => in_array('active', explode(' ', $anchor->getAttribute('class')), true),
            ];
        }

        return $links;
    }

    /** @return array{head: int, body: int} */
    private function stylePlacement(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        return [
            'head' => $xpath->query('//head/style')->length,
            'body' => $xpath->query('//body/style')->length,
        ];
    }
}
