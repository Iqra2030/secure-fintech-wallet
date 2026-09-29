<?php
namespace Tests;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
class BeneficiaryTest extends TestCase {
 use DatabaseMigrations;
 protected function setUp(): void { parent::setUp(); $this->seed(); }
 public function test_lookup_is_preview_and_confirmation_saves_only_for_actor(): void {
  $this->actingAs(User::find(1))->post('/beneficiaries/lookup',['email'=>'sara@wallet.test'])->assertOk()->assertSee('Sara')->assertSee('Confirm and add');
  $this->assertDatabaseCount('beneficiaries',0);
  $this->post('/beneficiaries',['beneficiary_user_id'=>2,'user_id'=>3])->assertRedirect('/beneficiaries');
  $this->assertDatabaseHas('beneficiaries',['user_id'=>1,'beneficiary_user_id'=>2]);
  $this->actingAs(User::find(3))->get('/beneficiaries')->assertOk()->assertSee('No beneficiaries yet');
 }
 public function test_duplicate_add_does_not_duplicate_or_reset_age(): void {
  $this->actingAs(User::find(1));
  $this->post('/beneficiaries',['beneficiary_user_id'=>2])->assertRedirect();
  $created=DB::table('beneficiaries')->value('created_at');
  $this->travel(2)->hours();
  $this->post('/beneficiaries',['beneficiary_user_id'=>2])->assertRedirect();
  $this->assertDatabaseCount('beneficiaries',1);
  $this->assertSame($created,DB::table('beneficiaries')->value('created_at'));
 }
 public function test_invalid_and_self_beneficiaries_are_rejected(): void {
  $this->actingAs(User::find(1));
  foreach([1,999] as $id)$this->postJson('/beneficiaries',['beneficiary_user_id'=>$id])->assertUnprocessable();
  $this->postJson('/beneficiaries/lookup',['email'=>'missing@wallet.test'])->assertUnprocessable();
  $this->postJson('/beneficiaries/lookup',['email'=>'ali@wallet.test'])->assertUnprocessable();
  $this->assertDatabaseCount('beneficiaries',0);
 }
 public function test_guest_cannot_lookup_add_or_remove(): void {
  $this->postJson('/beneficiaries/lookup',['email'=>'sara@wallet.test'])->assertUnauthorized();
  $this->postJson('/beneficiaries',['beneficiary_user_id'=>2])->assertUnauthorized();
  $this->deleteJson('/beneficiaries/1')->assertUnauthorized();
 }
 public function test_removal_is_owner_scoped_and_preserves_history(): void {
  $this->actingAs(User::find(1))->post('/beneficiaries',['beneficiary_user_id'=>2]);
  $id=DB::table('beneficiaries')->value('id');
  $this->postJson('/transfers',['receiver_id'=>2,'amount'=>'100','idempotency_key'=>'saved'])->assertOk();
  $this->actingAs(User::find(3))->deleteJson('/beneficiaries/'.$id)->assertNotFound();
  $this->assertDatabaseCount('beneficiaries',1);
  $this->actingAs(User::find(1))->delete('/beneficiaries/'.$id)->assertRedirect('/beneficiaries');
  $this->assertDatabaseCount('beneficiaries',0);
  $this->assertDatabaseCount('transfers',1);
  $this->assertDatabaseCount('ledger_entries',5);
  $this->postJson('/transfers',['receiver_id'=>2,'amount'=>'100','idempotency_key'=>'removed'])->assertUnprocessable();
  $this->assertSame(9990000,(int)DB::table('wallets')->where('user_id',1)->value('balance_minor'));
 }
 public function test_transfer_cannot_bypass_beneficiary_requirement(): void {
  $this->actingAs(User::find(1))->postJson('/transfers',['receiver_id'=>2,'amount'=>'100','idempotency_key'=>'not-saved'])->assertUnprocessable();
  $this->assertDatabaseCount('transfers',0);
  $this->assertSame(10000000,(int)DB::table('wallets')->where('user_id',1)->value('balance_minor'));
 }
}
