<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_view_activity_logs(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->get(route('activity-logs.index'))->assertRedirect(route('login'));

        foreach ([User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_CUSTOMER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('activity-logs.index'))
                ->assertForbidden();
        }

        $this->actingAs($superAdmin)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Activity Logs');
    }

    public function test_super_admin_sidebar_contains_activity_logs_and_settings_but_cashier_sidebar_does_not(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('activity-logs.index'), false)
            ->assertSee('Activity Logs')
            ->assertSee(route('settings.payment.edit'), false)
            ->assertSee('Settings')
            ->assertDontSee('Payment Settings');

        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)
            ->get(route('cashier'))
            ->assertOk()
            ->assertDontSee(route('activity-logs.index'), false)
            ->assertDontSee(route('settings.payment.edit'), false)
            ->assertDontSee('Activity Logs');
    }

    public function test_successful_staff_login_records_identity_and_request_context_without_the_password(): void
    {
        $password = 'AuditLoginPassword123!';
        $superAdmin = User::factory()->create([
            'name' => 'Audit Administrator',
            'email' => 'audit.admin@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => $password,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.24',
            'HTTP_USER_AGENT' => 'Kermits-Audit-Test/1.0',
        ])->post(route('login.store'), [
            'email' => $superAdmin->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $log = ActivityLog::query()
            ->where('action', 'auth.login')
            ->where('user_id', $superAdmin->id)
            ->sole();

        $this->assertSame('Audit Administrator', $log->actor_name);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $log->actor_role);
        $this->assertSame('Signed in', $log->description);
        $this->assertSame('login.store', $log->route_name);
        $this->assertSame('POST', $log->method);
        $this->assertSame('login', ltrim((string) $log->path, '/'));
        $this->assertSame(302, $log->status_code);
        $this->assertSame('203.0.113.24', $log->ip_address);
        $this->assertSame('Kermits-Audit-Test/1.0', $log->user_agent);
        $this->assertStringNotContainsString(
            $password,
            json_encode($log->getAttributes(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_failed_and_customer_logins_do_not_create_staff_login_entries(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'failed.audit@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'CorrectPassword123!',
        ]);
        $customer = User::factory()->create([
            'email' => 'customer.audit@example.com',
            'role' => User::ROLE_CUSTOMER,
            'password' => 'CustomerPassword123!',
        ]);

        $this->post(route('login.store'), [
            'email' => $superAdmin->email,
            'password' => 'IncorrectPassword123!',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'CustomerPassword123!',
        ])->assertRedirect(route('shop'));

        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'auth.login',
            'user_id' => $superAdmin->id,
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'auth.login',
            'user_id' => $customer->id,
        ]);
    }

    public function test_staff_logout_records_the_actor_before_authentication_is_cleared(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Signing Out Admin',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '2001:db8::42',
            'HTTP_USER_AGENT' => 'Kermits-Logout-Test/1.0',
        ])->actingAs($superAdmin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $log = ActivityLog::query()
            ->where('action', 'auth.logout')
            ->where('user_id', $superAdmin->id)
            ->sole();

        $this->assertSame('Signing Out Admin', $log->actor_name);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $log->actor_role);
        $this->assertSame('Signed out', $log->description);
        $this->assertSame('logout', $log->route_name);
        $this->assertSame('POST', $log->method);
        $this->assertSame('logout', ltrim((string) $log->path, '/'));
        $this->assertSame(302, $log->status_code);
        $this->assertSame('2001:db8::42', $log->ip_address);
        $this->assertSame('Kermits-Logout-Test/1.0', $log->user_agent);
    }

    public function test_state_changing_staff_request_is_logged_without_sensitive_request_values(): void
    {
        $password = 'NewCashierPassword123!';
        $superAdmin = User::factory()->create([
            'name' => 'Account Manager',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.77',
            'HTTP_USER_AGENT' => 'Kermits-Management-Test/1.0',
        ])->actingAs($superAdmin)
            ->post(route('cashiers.store'), [
                'name' => 'Logged Cashier',
                'username' => 'logged.cashier',
                'email' => 'logged.cashier@example.com',
                'phone' => '09171234567',
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertRedirect();

        $log = ActivityLog::query()
            ->where('route_name', 'cashiers.store')
            ->where('user_id', $superAdmin->id)
            ->sole();

        $this->assertNotSame('', trim((string) $log->action));
        $this->assertSame('Account Manager', $log->actor_name);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $log->actor_role);
        $this->assertSame('POST', $log->method);
        $this->assertSame('staff/cashiers', ltrim((string) $log->path, '/'));
        $this->assertSame(302, $log->status_code);
        $this->assertSame('198.51.100.77', $log->ip_address);
        $this->assertSame('Kermits-Management-Test/1.0', $log->user_agent);

        $serializedLog = json_encode($log->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($password, $serializedLog);
        $this->assertStringNotContainsString('password_confirmation', $serializedLog);
    }

    public function test_read_only_staff_requests_are_not_logged(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_activity_page_displays_metadata_newest_first_and_escapes_untrusted_values(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $oldest = $this->createActivityLog([
            'description' => 'Oldest activity',
            'ip_address' => '192.0.2.10',
        ]);
        $oldest->forceFill(['created_at' => now()->subMinutes(2)])->saveQuietly();

        $newest = $this->createActivityLog([
            'action' => 'auth.logout',
            'description' => 'Newest activity',
            'route_name' => 'logout',
            'path' => 'logout',
            'ip_address' => '2001:db8::99',
            'user_agent' => 'Kermits-Browser/2.0',
        ]);
        $newest->forceFill(['created_at' => now()->subMinute()])->saveQuietly();

        $unsafeDescription = '<script>alert("audit")</script>';
        $unsafeAgent = '<img src=x onerror=alert("agent")>';
        $unsafe = $this->createActivityLog([
            'description' => $unsafeDescription,
            'ip_address' => '203.0.113.88',
            'user_agent' => $unsafeAgent,
        ]);
        $unsafe->forceFill(['created_at' => now()])->saveQuietly();

        $response = $this->actingAs($superAdmin)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSeeInOrder([$unsafeDescription, 'Newest activity', 'Oldest activity'])
            ->assertSee('2001:db8::99')
            ->assertSee('Kermits-Browser/2.0')
            ->assertSee('POST')
            ->assertSee('logout')
            ->assertSee(e($unsafeDescription), false)
            ->assertSee(e($unsafeAgent), false);

        $response->assertDontSee($unsafeDescription, false)
            ->assertDontSee($unsafeAgent, false);
    }

    public function test_activity_logs_can_be_filtered_without_leaking_unmatched_rows(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->createActivityLog([
            'action' => 'auth.login',
            'description' => 'Matching sign in',
            'ip_address' => '198.51.100.25',
        ]);
        $this->createActivityLog([
            'action' => 'inventory.update',
            'description' => 'Unmatched inventory change',
            'ip_address' => '203.0.113.60',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('activity-logs.index', ['search' => '198.51.100.25']))
            ->assertOk()
            ->assertSee('Matching sign in')
            ->assertDontSee('Unmatched inventory change');

        $this->actingAs($superAdmin)
            ->get(route('activity-logs.index', ['action' => 'inventory.update']))
            ->assertOk()
            ->assertSee('Unmatched inventory change')
            ->assertDontSee('Matching sign in');
    }

    private function createActivityLog(array $overrides = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => null,
            'actor_name' => 'Audit Actor',
            'actor_role' => User::ROLE_SUPER_ADMIN,
            'action' => 'inventory.update',
            'description' => 'Inventory updated',
            'route_name' => 'inventory.update',
            'method' => 'POST',
            'path' => 'inventory/1',
            'status_code' => 302,
            'ip_address' => '192.0.2.1',
            'user_agent' => 'Kermits-Test/1.0',
            ...$overrides,
        ]);
    }
}
