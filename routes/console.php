<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('lab:sign-payment {event} {user} {amount} {--age=0 : Seconds to subtract for expiry testing}',function(){
 $secret=config('lab.webhook_secret');if(!$secret){$this->error('Set WEBHOOK_SECRET in .env');return 1;}
 $body=json_encode(['event_id'=>$this->argument('event'),'user_id'=>(int)$this->argument('user'),'amount_minor'=>(int)$this->argument('amount')],JSON_UNESCAPED_SLASHES);
 $age=max(0,(int)$this->option('age'));
 $timestamp=(string)(time()-$age);
 $this->line('POST /webhooks/payment');$this->line('Content-Type: application/json');
 $this->line('X-Payment-Timestamp: '.$timestamp);$this->line('X-Payment-Signature: '.hash_hmac('sha256',$timestamp.'.'.$body,$secret));$this->newLine();$this->line($body);
})->purpose('Produce a signed simulator request for Burp Repeater (amount in paisa)');
