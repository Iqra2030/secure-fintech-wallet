<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->rememberToken();$t->timestamps();});
  Schema::create('wallets',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained();$t->bigInteger('balance_minor')->default(0);$t->timestamps();});
  Schema::create('transfers',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('sender_id')->constrained('users');$t->foreignId('receiver_id')->constrained('users');$t->bigInteger('amount_minor');$t->string('status')->default('completed');$t->string('idempotency_key',100)->nullable();$t->string('request_hash',64)->nullable();$t->timestamps();if(!config('lab.vulnerable'))$t->unique(['sender_id','idempotency_key']);});
  Schema::create('payment_events',function(Blueprint $t){$t->id();$t->string('event_id',100);if(!config('lab.vulnerable'))$t->unique('event_id');$t->string('payload_hash',64);$t->foreignId('user_id')->constrained();$t->bigInteger('amount_minor');$t->timestamps();});
  Schema::create('ledger_entries',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained();$t->uuid('transfer_id')->nullable();$t->foreign('transfer_id')->references('id')->on('transfers');$t->unsignedBigInteger('payment_event_id')->nullable();$t->foreign('payment_event_id')->references('id')->on('payment_events');$t->bigInteger('amount_minor');$t->string('description');$t->timestamp('created_at');});
  Schema::create('audit_events',function(Blueprint $t){$t->id();$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->string('outcome');$t->string('reference')->nullable();$t->string('ip',45)->nullable();$t->timestamp('created_at');});
  Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
  Schema::create('cache',function(Blueprint $t){$t->string('key')->primary();$t->mediumText('value');$t->integer('expiration');});
  Schema::create('cache_locks',function(Blueprint $t){$t->string('key')->primary();$t->string('owner');$t->integer('expiration');});
  if (!config('lab.vulnerable')) {
   DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallet_nonnegative CHECK (balance_minor >= 0)');
   DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfer_positive CHECK (amount_minor > 0)');
   DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfer_distinct CHECK (sender_id <> receiver_id)');
   DB::statement('ALTER TABLE payment_events ADD CONSTRAINT payment_positive CHECK (amount_minor > 0)');
  }
 }
 public function down(): void {foreach(['cache_locks','cache','sessions','audit_events','ledger_entries','payment_events','transfers','wallets','users'] as $table)Schema::dropIfExists($table);}
};
