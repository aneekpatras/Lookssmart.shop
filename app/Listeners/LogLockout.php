<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\AccountLockedOut;
use Illuminate\Auth\Events\Lockout;
use Laravel\Fortify\Fortify;

class LogLockout
{
    public function handle(Lockout $event): void
    {
        $email = $event->request->input(Fortify::username());

        if ($email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                dispatch(fn () => $user->notify(new AccountLockedOut))->afterResponse();
            }
        }
    }
}
