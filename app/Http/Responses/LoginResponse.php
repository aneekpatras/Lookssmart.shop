<?php

namespace App\Http\Responses;

use App\Models\User;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        /** @var User $user */
        $user = $request->user();

        $destination = $user->hasAnyRole(['super-admin', 'admin', 'receptionist', 'staff'])
            ? '/admin'
            : '/my-account';

        return redirect()->intended($destination);
    }
}
