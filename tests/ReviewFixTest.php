<?php

namespace Tests;

use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

class ReviewFixTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        DB::table('beneficiaries')->insert([
            'user_id' => 1, 'beneficiary_user_id' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::find(1));
    }

    private function payload(array $changes = []): array
    {
        return array_merge(['receiver_id' => 2, 'amount' => '100', 'idempotency_key' => 'review-operation'], $changes);
    }

    public function test_history_shows_required_fields_and_scopes_to_customer(): void
    {
        $id = $this->postJson('/transfers', $this->payload())->assertOk()->json('id');
        $this->get('/dashboard')->assertOk()->assertSee('Transfer history')->assertSee('Sender')
            ->assertSee('Receiver')->assertSee('Status')->assertSee('Ali')->assertSee('Sara')
            ->assertSee('Completed')->assertSee($id);
        $this->actingAs(User::find(3))->get('/dashboard')->assertOk()->assertDontSee($id);
    }

    public function test_public_responses_omit_internal_fields(): void
    {
        $this->postJson('/transfers', $this->payload())->assertOk()
            ->assertJsonMissingPath('idempotency_key')->assertJsonMissingPath('request_hash')
            ->assertJsonStructure(['id', 'sender_id', 'receiver_id', 'amount_minor', 'status', 'created_at']);
        $this->getJson('/wallets/1')->assertOk()->assertJsonMissingPath('wallet.id')
            ->assertJsonMissingPath('transactions.0.idempotency_key')->assertJsonMissingPath('transactions.0.request_hash')
            ->assertJsonPath('transactions.0.sender_name', 'Ali')->assertJsonPath('transactions.0.receiver_name', 'Sara');
    }

    public function test_rejected_transfer_requests_are_audited_without_payload_secrets(): void
    {
        $this->postJson('/transfers', $this->payload(['amount' => 'bad']))->assertUnprocessable();
        $this->postJson('/transfers', $this->payload(['amount' => '100001']))->assertUnprocessable();
        $this->assertSame(2, DB::table('audit_events')->where('action', 'transfer')->where('outcome', 'validation_rejected')->count());
        $this->assertSame(0, DB::table('transfers')->count());
        $this->assertSame(10000000, (int) DB::table('wallets')->where('user_id', 1)->value('balance_minor'));
        $this->assertFalse(DB::table('audit_events')->where('reference', 'review-operation')->exists());
        if (!config('lab.vulnerable')) {
            $this->postJson('/transfers', $this->payload())->assertOk();
            $this->postJson('/transfers', $this->payload(['amount' => '200']))->assertStatus(409);
            $this->assertDatabaseHas('audit_events', ['actor_id' => 1, 'action' => 'transfer', 'outcome' => 'idempotency_conflict']);
        }
    }

    public function test_failure_audit_survives_secured_rollback(): void
    {
        try {
            app(TransferService::class)->send(1, 2, 10000, 'failure-review', fn () => throw new \RuntimeException('injected'));
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('injected', $error->getMessage());
        }
        $this->assertDatabaseHas('audit_events', ['actor_id' => 1, 'action' => 'transfer', 'outcome' => 'processing_failed']);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'transfer')->where('outcome', 'completed')->count());
        $this->assertSame(config('lab.vulnerable') ? 9990000 : 10000000, (int) DB::table('wallets')->where('user_id', 1)->value('balance_minor'));
    }

    public function test_login_page_does_not_publish_seed_password_and_sessions_encrypt(): void
    {
        auth()->logout();
        $this->get('/login')->assertOk()->assertDontSee('WalletLab!2026');
        $this->assertTrue(config('session.encrypt'));
    }
}
