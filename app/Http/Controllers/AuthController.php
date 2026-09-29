<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController {
 public function login(Request $r) {
  $data=$r->validate(['email'=>'required|email|max:255','password'=>'required|string|max:255']);
  $data['email']=strtolower($data['email']);
  $key='login:'.hash('sha256',$data['email'].'|'.$r->ip());
  $ipKey='login-ip:'.$r->ip();
  if(!config('lab.vulnerable') && (RateLimiter::tooManyAttempts($key,5)||RateLimiter::tooManyAttempts($ipKey,30))){Audit::write('login','throttled');return response()->json(['message'=>'Too many attempts. Try again in one minute.'],429,['Retry-After'=>'60']);}
  if(!config('lab.vulnerable')){RateLimiter::hit($key,60);RateLimiter::hit($ipKey,60);}
  if(!Auth::attempt($data)){Audit::write('login','denied');throw ValidationException::withMessages(['email'=>'Invalid email or password.']);}
  RateLimiter::clear($key);
  $r->session()->regenerate();Audit::write('login','success');return redirect('/dashboard');
 }
 public function register(Request $r) {
  $r->merge(['email'=>strtolower((string)$r->input('email'))]);
  $data=$r->validate(['name'=>'required|string|max:80','email'=>'required|email|max:255|unique:users','password'=>'required|string|min:12|max:255|confirmed']);
  $user=DB::transaction(function()use($data){$u=User::create($data);DB::table('wallets')->insert(['user_id'=>$u->id,'balance_minor'=>0,'created_at'=>now(),'updated_at'=>now()]);return $u;});
  Auth::login($user);$r->session()->regenerate();Audit::write('register','success');return redirect('/dashboard');
 }
 public function logout(Request $r) {Audit::write('logout','success');Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
}
