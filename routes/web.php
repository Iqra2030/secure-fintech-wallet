<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,WalletController,WebhookController,BeneficiaryController};
Route::get('/',fn()=>redirect('/dashboard'));
Route::middleware('guest')->group(function(){
 Route::view('/login','login')->name('login');Route::post('/login',[AuthController::class,'login']);
 Route::view('/register','register');Route::post('/register',[AuthController::class,'register'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function(){
 Route::get('/beneficiaries',[BeneficiaryController::class,'index']);
 Route::post('/beneficiaries/lookup',[BeneficiaryController::class,'lookup'])->middleware('throttle:10,1');
 Route::post('/beneficiaries',[BeneficiaryController::class,'store'])->middleware('throttle:20,1');
 Route::delete('/beneficiaries/{beneficiary}',[BeneficiaryController::class,'destroy'])->whereNumber('beneficiary');
 Route::get('/dashboard',[WalletController::class,'dashboard']);
 Route::get('/wallets/{owner}',[WalletController::class,'show'])->whereNumber('owner');
 Route::post('/transfers',[WalletController::class,'transfer']);
 Route::post('/logout',[AuthController::class,'logout']);
});
Route::post('/webhooks/payment',WebhookController::class)->middleware('throttle:30,1');
