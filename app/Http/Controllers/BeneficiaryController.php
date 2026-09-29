<?php
namespace App\Http\Controllers;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BeneficiaryController {
 public function index(Request $r) { return $this->page($r); }
 private function page(Request $r, ?object $candidate = null) {
  $beneficiaries = DB::table('beneficiaries as b')->join('users as u','u.id','=','b.beneficiary_user_id')
   ->where('b.user_id',$r->user()->id)->orderBy('u.name')->get(['b.id','u.name','u.email','b.created_at']);
  return view('beneficiaries', compact('beneficiaries','candidate'));
 }
 public function lookup(Request $r) {
  $data = $r->validate(['email'=>'required|email|max:255']);
  $candidate = DB::table('users')->where('email',strtolower(trim($data['email'])))
   ->where('id','!=',$r->user()->id)->first(['id','name','email']);
  if (!$candidate) throw ValidationException::withMessages(['email'=>'No other registered customer matches this email.']);
  // Preview only: nothing is saved until the customer confirms.
  return $this->page($r, $candidate);
 }
 public function store(Request $r) {
  $data = $r->validate(['beneficiary_user_id'=>'required|integer|exists:users,id']);
  $recipient = (int)$data['beneficiary_user_id'];
  if ($recipient === $r->user()->id) throw ValidationException::withMessages(['beneficiary_user_id'=>'You cannot add yourself.']);
  DB::transaction(function () use ($r,$recipient) {
   $inserted = DB::table('beneficiaries')->insertOrIgnore(['user_id'=>$r->user()->id,'beneficiary_user_id'=>$recipient,'created_at'=>now(),'updated_at'=>now()]);
   if ($inserted) Audit::write('beneficiary.add','completed',(string)$recipient);
  });
  return redirect('/beneficiaries')->with('success','Beneficiary saved. You can now select them when sending money.');
 }
 public function destroy(Request $r, int $beneficiary) {
  DB::transaction(function () use ($r,$beneficiary) {
   $record = DB::table('beneficiaries')->where('id',$beneficiary)->where('user_id',$r->user()->id)->lockForUpdate()->first();
   abort_unless($record,404);
   DB::table('beneficiaries')->where('id',$record->id)->delete();
   Audit::write('beneficiary.remove','completed',(string)$record->beneficiary_user_id);
  });
  return redirect('/beneficiaries')->with('success','Beneficiary removed. Previous transactions remain in your history.');
 }
}
