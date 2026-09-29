<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
 ->withMiddleware(function (Middleware $middleware): void {
   $middleware->prepend(App\Http\Middleware\LocalLabOnly::class);
   $middleware->validateCsrfTokens(except: ['webhooks/payment']);
   $middleware->redirectGuestsTo('/login');
   $middleware->redirectUsersTo('/dashboard');
 })
 ->withExceptions(function (Exceptions $exceptions): void {})
 ->create();
