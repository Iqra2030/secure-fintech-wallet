<?php
namespace Tests;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class WalletTest extends TestCase {
 use DatabaseMigrations;
 protected function setUp(): void {parent::setUp();$this->seed();}
 private function balance(int $id): int {return (int)DB::table('wallets')->where('user_id',$id)->value('balance_minor');}
 private function payload(array $extra=[]): array {return array_merge(['receiver_id'=>2,'amount'=>'2000.00','idempotency_key'=>'test-key'],$extra);}
 public function test_owner_and_other_customer_access(): void {
  $this->actingAs(User::find(1))->getJson('/wallets/1')->assertOk();
  $response=$this->getJson('/wallets/2');$response->assertStatus(config('lab.vulnerable')?200:403);
 }
 public function test_negative_transfer_changes_only_vulnerable_wallet(): void {
  $r=$this->actingAs(User::find(1))->postJson('/transfers',$this->payload(['amount'=>'-100.00']));
  $r->assertStatus(config('lab.vulnerable')?200:422);
  $this->assertSame(config('lab.vulnerable')?10010000:10000000,$this->balance(1));
  $this->assertSame(config('lab.vulnerable')?9990000:10000000,$this->balance(2));
 }
 public function test_replaying_transfer_moves_money_once_only_when_secured(): void {
  $this->actingAs(User::find(1));
  $this->postJson('/transfers',$this->payload())->assertOk();
  $this->postJson('/transfers',$this->payload())->assertOk();
  $this->assertSame(config('lab.vulnerable')?9600000:9800000,$this->balance(1));
  $this->assertSame(config('lab.vulnerable')?10400000:10200000,$this->balance(2));
  $this->assertSame(config('lab.vulnerable')?2:1,DB::table('transfers')->count());
 }
 public function test_same_key_different_payload_conflicts_in_secured_version(): void {
  $this->actingAs(User::find(1));$this->postJson('/transfers',$this->payload())->assertOk();
  $this->postJson('/transfers',$this->payload(['amount'=>'3000']))->assertStatus(config('lab.vulnerable')?200:409);
 }
 public function test_unauthenticated_transfer_cannot_move_money(): void {
  $this->postJson('/transfers',$this->payload())->assertUnauthorized();$this->assertSame(10000000,$this->balance(1));
 }
 public function test_insufficient_funds_leave_both_wallets_unchanged(): void {
  $this->actingAs(User::find(1))->postJson('/transfers',$this->payload(['amount'=>'100001']))->assertUnprocessable();
  $this->assertSame(10000000,$this->balance(1));$this->assertSame(10000000,$this->balance(2));
 }
 public function test_login_is_throttled_only_in_secured_version(): void {
  for($i=0;$i<5;$i++)$this->postJson('/login',['email'=>'ali@wallet.test','password'=>'wrong'])->assertUnprocessable();
  $this->postJson('/login',['email'=>'ali@wallet.test','password'=>'wrong'])->assertStatus(config('lab.vulnerable')?422:429);
 }
 public function test_unsigned_webhook_cannot_credit_secured_wallet(): void {
  $this->postJson('/webhooks/payment',['event_id'=>'fake','user_id'=>1,'amount_minor'=>50000])->assertStatus(config('lab.vulnerable')?200:401);
  $this->assertSame(config('lab.vulnerable')?10050000:10000000,$this->balance(1));
 }
 private function signed(string $body, ?int $time=null) {
  $time=$time??time();return $this->call('POST','/webhooks/payment',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_PAYMENT_TIMESTAMP'=>(string)$time,'HTTP_X_PAYMENT_SIGNATURE'=>hash_hmac('sha256',$time.'.'.$body,config('lab.webhook_secret'))],$body);
 }
 public function test_signed_webhook_replay(): void {
  $body=json_encode(['event_id'=>'real-1','user_id'=>1,'amount_minor'=>50000]);
  $this->signed($body)->assertOk();$this->signed($body)->assertOk();
  $this->assertSame(config('lab.vulnerable')?10100000:10050000,$this->balance(1));
 }
 public function test_expired_signature(): void {
  $this->signed(json_encode(['event_id'=>'old','user_id'=>1,'amount_minor'=>50000]),time()-600)->assertStatus(config('lab.vulnerable')?200:401);
 }
 public function test_partial_failure_rolls_back_only_in_secured_version(): void {
  try{app(TransferService::class)->send(1,2,200000,'failure',fn()=>throw new \RuntimeException('Test failure'));$this->fail('Failure expected');}catch(\RuntimeException $e){$this->assertSame('Test failure',$e->getMessage());}
  $this->assertSame(config('lab.vulnerable')?9800000:10000000,$this->balance(1));$this->assertSame(10000000,$this->balance(2));
  $this->assertSame(0,DB::table('transfers')->count());
 }
 public function test_registration_starts_at_zero_and_hashes_password(): void {
  $this->post('/register',['name'=>'Test','email'=>'test@example.test','password'=>'ExamplePass!123','password_confirmation'=>'ExamplePass!123'])->assertRedirect('/dashboard');
  $user=User::where('email','test@example.test')->first();$this->assertNotSame('ExamplePass!123',$user->password);$this->assertSame(0,$this->balance($user->id));
 }
 public function test_logout_revokes_session_access(): void {
  $this->actingAs(User::find(1))->post('/logout')->assertRedirect('/login');$this->getJson('/wallets/1')->assertUnauthorized();
 }
 public function test_pages_render(): void {
  $this->get('/login')->assertOk()->assertSee('Welcome back');
  $this->get('/register')->assertOk()->assertSee('Create your wallet');
  $this->actingAs(User::find(1))->get('/dashboard')->assertOk()->assertSee('Send money');
 }
 public function test_tampered_webhook_body(): void {
  $timestamp=(string)time();$body=json_encode(['event_id'=>'tampered','user_id'=>1,'amount_minor'=>50000]);
  $changed=json_encode(['event_id'=>'tampered','user_id'=>1,'amount_minor'=>90000]);
  $this->call('POST','/webhooks/payment',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_PAYMENT_TIMESTAMP'=>$timestamp,'HTTP_X_PAYMENT_SIGNATURE'=>hash_hmac('sha256',$timestamp.'.'.$body,config('lab.webhook_secret'))],$changed)->assertStatus(config('lab.vulnerable')?200:401);
  $this->assertSame(config('lab.vulnerable')?10090000:10000000,$this->balance(1));
 }
 public function test_transfer_preserves_total_and_matches_ledger(): void {
  $this->actingAs(User::find(1))->postJson('/transfers',$this->payload())->assertOk();
  $this->assertSame(30000000,(int)DB::table('wallets')->sum('balance_minor'));
  foreach([1,2,3] as $id)$this->assertSame($this->balance($id),(int)DB::table('ledger_entries')->where('user_id',$id)->sum('amount_minor'));
 }
 public function test_secured_self_transfer_is_rejected(): void {
  if(config('lab.vulnerable')){$this->markTestSkipped('Secured self-transfer invariant.');}
  $this->actingAs(User::find(1))->postJson('/transfers',$this->payload(['receiver_id'=>1]))->assertUnprocessable();
  $this->assertSame(10000000,$this->balance(1));
 }
}
