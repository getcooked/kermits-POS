<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_pages_share_one_three_tab_account_section(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin);

        foreach ([
            'superadmin.security.edit' => 'Admin Account',
            'cashiers.index' => 'Cashier Accounts',
            'customers.index' => 'Customers',
        ] as $routeName => $label) {
            $response = $this->get(route($routeName))->assertOk();

            $response
                ->assertSee('aria-label="Account sections"', false)
                ->assertSee(route('superadmin.security.edit'), false)
                ->assertSee(route('cashiers.index'), false)
                ->assertSee(route('customers.index'), false)
                ->assertSee('class="active" href="'.route($routeName).'">'.$label, false);
        }
    }
}
