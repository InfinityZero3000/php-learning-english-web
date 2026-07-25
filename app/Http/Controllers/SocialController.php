<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Google Login
    |--------------------------------------------------------------------------
    */

    public function google()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $googleDriver */
            $googleDriver = Socialite::driver('google');
            $googleUser = $googleDriver->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            Auth::login($user);

            return redirect('/');
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return redirect()->route('login')
                ->with('error', 'Google login failed. Please try again.');
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'An error occurred during login. Please try again.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Facebook Login
    |--------------------------------------------------------------------------
    */

    public function facebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function facebookCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\FacebookProvider $facebookDriver */
            $facebookDriver = Socialite::driver('facebook');
            $facebookUser = $facebookDriver->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $facebookUser->getEmail()],
                [
                    'name' => $facebookUser->getName(),
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            Auth::login($user);

            return redirect('/');
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return redirect()->route('login')
                ->with('error', 'Facebook login failed. Please try again.');
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'An error occurred during login. Please try again.');
        }
    }
}
