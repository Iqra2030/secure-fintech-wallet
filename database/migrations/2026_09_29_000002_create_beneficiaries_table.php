<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('beneficiaries', function (Blueprint $t) {
   $t->id();
   $t->foreignId('user_id')->constrained('users');
   $t->foreignId('beneficiary_user_id')->constrained('users');
   $t->timestamps();
   $t->unique(['user_id', 'beneficiary_user_id']);
  });
  DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT beneficiary_not_self CHECK (user_id <> beneficiary_user_id)');
 }
 public function down(): void { Schema::dropIfExists('beneficiaries'); }
};
