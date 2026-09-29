<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class LocalLabOnly {
 public function handle(Request $request, Closure $next) {
  // Both editions are teaching labs, served on loopback. No trusted-proxy bypass.
  abort_unless(app()->environment(['local','testing']) && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1','::1']), 403, 'Local laboratory only.');
  $response = $next($request);
  $response->headers->set('Cache-Control','no-store, private');
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','same-origin');
  return $response;
 }
}
