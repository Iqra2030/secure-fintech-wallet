<?php
namespace App\Services;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class TransferService {
 public function send(int $sender, int $receiver, int $amount, string $key, ?Closure $afterDebit=null): object {
  $hash=hash('sha256',json_encode([$sender,$receiver,$amount]));
  $operation=function() use($sender,$receiver,$amount,$key,$hash,$afterDebit) {
   $q=DB::table('wallets')->whereIn('user_id',[$sender,$receiver])->orderBy('user_id');
   if(!config('lab.vulnerable'))$q->lockForUpdate();
   $wallets=$q->get()->keyBy('user_id');
   if(!isset($wallets[$sender],$wallets[$receiver]))throw ValidationException::withMessages(['receiver_id'=>'Wallet not found.']);
   if(!config('lab.vulnerable')) {
    // Sender lock serializes even simultaneous uses of the same key.
    $existing=DB::table('transfers')->where('sender_id',$sender)->where('idempotency_key',$key)->first();
    if($existing){abort_unless(hash_equals($existing->request_hash,$hash),409,'Idempotency key reused with different details.');return $existing;}
    if($amount<=0 || $amount>100000000 || $sender===$receiver)throw ValidationException::withMessages(['amount'=>'Invalid transfer.']);
   }
   $beneficiaryQuery=DB::table('beneficiaries')->where('user_id',$sender)->where('beneficiary_user_id',$receiver);
   if(!config('lab.vulnerable'))$beneficiaryQuery->lockForUpdate();
   if(!$beneficiaryQuery->first())throw ValidationException::withMessages(['receiver_id'=>'Add this customer as a beneficiary before sending money.']);
   if($wallets[$sender]->balance_minor<$amount)throw ValidationException::withMessages(['amount'=>'Insufficient funds.']);
   $id=(string)Str::uuid();
   DB::table('wallets')->where('user_id',$sender)->decrement('balance_minor',$amount,['updated_at'=>now()]);
   if($afterDebit)$afterDebit(); // Test-only injection; never controlled by an HTTP parameter.
   DB::table('wallets')->where('user_id',$receiver)->increment('balance_minor',$amount,['updated_at'=>now()]);
   DB::table('transfers')->insert(['id'=>$id,'sender_id'=>$sender,'receiver_id'=>$receiver,'amount_minor'=>$amount,'idempotency_key'=>$key,'request_hash'=>$hash,'status'=>'completed','created_at'=>now(),'updated_at'=>now()]);
   DB::table('ledger_entries')->insert([
    ['user_id'=>$sender,'transfer_id'=>$id,'amount_minor'=>-$amount,'description'=>'Transfer sent','created_at'=>now()],
    ['user_id'=>$receiver,'transfer_id'=>$id,'amount_minor'=>$amount,'description'=>'Transfer received','created_at'=>now()]
   ]);
   Audit::write('transfer','completed',$id);
   return DB::table('transfers')->where('id',$id)->first();
  };
  return config('lab.vulnerable') ? $operation() : DB::transaction($operation,3);
 }
}
