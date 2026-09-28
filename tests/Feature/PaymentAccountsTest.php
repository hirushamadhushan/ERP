<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\PaymentAccountDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_with_deposits_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'name' => 'Deposit Test Account',
            'account_number' => 'DEP-001',
            'opening_balance' => 0,
            'current_balance' => 250,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        PaymentAccountDeposit::create([
            'request_id' => 'd8f36a01-8ea9-4e50-af6f-cc7514d6291e',
            'account_id' => $account->id,
            'amount' => 250,
            'deposited_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('payment-accounts.destroy', $account))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Accounts with deposits cannot be deleted.');

        $this->assertDatabaseHas('payment_accounts', ['id' => $account->id]);
    }

    public function test_a_deposit_request_id_cannot_be_reused_with_different_financial_data(): void
    {
        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'name' => 'Cash Till', 'account_number' => 'CASH-001',
            'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'created_by' => $user->id,
        ]);
        $requestId = 'a4e1b49c-1830-4c89-a66b-5ff52a42725e';
        $payload = [
            'request_id' => $requestId, 'account_id' => $account->id,
            'amount' => '100.00', 'date' => '2026-09-28T10:00',
        ];

        $this->actingAs($user)
            ->postJson(route('payment-accounts.deposits.store'), $payload)
            ->assertOk();

        $this->postJson(route('payment-accounts.deposits.store'), array_replace($payload, ['amount' => '250.00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_id');

        $this->assertSame('100.00', $account->fresh()->current_balance);
        $this->assertDatabaseCount('payment_account_deposits', 1);
    }
}
