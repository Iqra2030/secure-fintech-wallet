<?php
namespace App\Http\Controllers;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WebhookController {
 public function __invoke(Request $r) {
  $raw=$r->getContent();
  if(!config('lab.vulnerable')){
   $secret=config('lab.webhook_secret');abort_unless(is_string($secret)&&strlen($secret)>=32,503,'Webhook secret not configured.');
   $timestamp=(string)$r->header('X-Payment-Timestamp','');
   $signature=(string)$r->header('X-Payment-Signature','');
   if(!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>300||!hash_equals(hash_hmac('sha256',$timestamp.'.'.$raw,$secret),$signature)){
    Audit::write('webhook','denied');abort(401,'Invalid payment signature or timestamp.');
   }
  }
  $data=$r->validate(['event_id'=>'required|string|max:100','user_id'=>'required|integer|exists:users,id','amount_minor'=>'required|integer|min:1|max:100000000']);
  $hash=hash('sha256',json_encode([(int)$data['user_id'],(int)$data['amount_minor']]));
  $apply=function()use($data,$hash){
   if(!config('lab.vulnerable')){
    // Serialize event identity, including conflicting requests for different wallets.
    DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',[$data['event_id']]);
    $prior=DB::table('payment_events')->where('event_id',$data['event_id'])->first();
    if($prior){abort_unless(hash_equals($prior->payload_hash,$hash),409,'Event ID reused with different content.');return response()->json(['status'=>'already_processed']);}
   }
   $id=DB::table('payment_events')->insertGetId([...$data,'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('wallets')->where('user_id',$data['user_id'])->increment('balance_minor',$data['amount_minor'],['updated_at'=>now()]);
   DB::table('ledger_entries')->insert(['user_id'=>$data['user_id'],'payment_event_id'=>$id,'amount_minor'=>$data['amount_minor'],'description'=>'Simulated gateway credit','created_at'=>now()]);
   Audit::write('webhook','credited',$data['event_id']);return response()->json(['status'=>'credited']);
  };
  return config('lab.vulnerable')?$apply():DB::transaction($apply,3);
 }
}
