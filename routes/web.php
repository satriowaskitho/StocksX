<?php

use App\Http\Controllers\SendMailController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function (){
    return to_route('filament.admin.auth.login');
})->name('login');

// ✅ Google OAuth Routes

Route::get('/auth/google/redirect', function () {
  return Socialite::driver('google')->redirect();
})->name('google.redirect');

Route::get('/auth/google/callback', function () {
  $googleUser = Socialite::driver('google')->stateless()->user();

  $user = User::firstOrCreate(
      ['email' => $googleUser->getEmail()],
      [
          'name' => $googleUser->getName(),
          'password' => bcrypt(str()->random(16)), // Optional, random password
          'email_verified_at' => now(),
      ]
  );

  Auth::login($user);

  return redirect()->intended(route('filament.admin.pages.dashboard'));
  // return redirect()->route('filament.admin.pages.dashboard');
});