<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
class AppServiceProvider extends ServiceProvider {
 public function boot(): void {
  Gate::define('view-wallet', fn(User $user, int $owner) => $user->id === $owner);
 }
}
