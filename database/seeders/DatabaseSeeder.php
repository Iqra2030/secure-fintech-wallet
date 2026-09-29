<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  foreach (['Ali','Sara','Ahmed'] as $name) {
   $user=User::create(['name'=>$name,'email'=>strtolower($name).'@wallet.test','password'=>'WalletLab!2026']);
   DB::table('wallets')->insert(['user_id'=>$user->id,'balance_minor'=>10000000,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('ledger_entries')->insert(['user_id'=>$user->id,'amount_minor'=>10000000,'description'=>'Synthetic opening balance','created_at'=>now()]);
  }
 }
}
