<?php
namespace App\Http\Controllers;
use App\Services\Audit;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
class WalletController {
 public function dashboard(Request $r) {
  return view('dashboard',['wallet'=>DB::table('wallets')->where('user_id',$r->user()->id)->first(),'customers'=>DB::table('users')->whereIn('id',DB::table('beneficiaries')->select('beneficiary_user_id')->where('user_id',$r->user()->id))->orderBy('name')->get(['id','name','email']),'entries'=>DB::table('ledger_entries')->where('user_id',$r->user()->id)->orderByDesc('id')->limit(20)->get()]);
 }
 public function show(int $owner) {
  if(!config('lab.vulnerable') && Gate::denies('view-wallet',$owner)){Audit::write('wallet.read','denied',(string)$owner);abort(403);}
  $wallet=DB::table('wallets')->where('user_id',$owner)->first();abort_unless($wallet,404);
  return response()->json(['wallet'=>$wallet,'transactions'=>DB::table('transfers')->where(fn($q)=>$q->where('sender_id',$owner)->orWhere('receiver_id',$owner))->orderByDesc('created_at')->limit(50)->get()]);
 }
 public function transfer(Request $r,TransferService $service) {
  if(!config('lab.vulnerable')){
   $key='transfer:'.$r->user()->id;
   if(RateLimiter::tooManyAttempts($key,10)){Audit::write('transfer','throttled');abort(429,'Transfer rate limit reached.');}
   RateLimiter::hit($key,60);
  }
  $data=$r->validate(['receiver_id'=>'required|integer|exists:users,id|different:sender_id','amount'=>config('lab.vulnerable')?'required|regex:/^-?[0-9]{1,7}(\.[0-9]{1,2})?$/':'required|regex:/^[0-9]{1,7}(\.[0-9]{1,2})?$/|numeric|gt:0|lte:1000000','idempotency_key'=>'required|string|max:100']);
  // Parse decimal string without floating-point arithmetic.
  $negative=str_starts_with($data['amount'],'-');
  $parts=explode('.',ltrim($data['amount'],'-'));
  $minor=((int)$parts[0]*100)+(int)str_pad($parts[1]??'',2,'0');
  if($negative)$minor=-$minor;
  $transfer=$service->send($r->user()->id,(int)$data['receiver_id'],$minor,$data['idempotency_key']);
  return $r->expectsJson()?response()->json($transfer):redirect('/dashboard')->with('success','Transfer completed. Reference: '.$transfer->id);
 }
}
