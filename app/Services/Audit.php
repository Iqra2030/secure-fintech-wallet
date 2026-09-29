<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function write(string $action, string $outcome, ?string $reference=null): void {
  DB::table('audit_events')->insert(['actor_id'=>auth()->id(),'action'=>$action,'outcome'=>$outcome,'reference'=>$reference,'ip'=>request()->ip(),'created_at'=>now()]);
 }
}
