<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user, string $channel): RedirectResponse
    {
        abort_unless(in_array($channel, ['mail', 'sms', 'whatsapp'], true), 404);

        $profile = $user->customerProfile()->firstOrCreate([]);
        $field = match ($channel) {
            'mail' => 'email_opt_out',
            'sms' => 'sms_opt_out',
            'whatsapp' => 'whatsapp_opt_out',
        };

        $profile->forceFill([$field => true])->save();

        return redirect()->back()->with('status', 'Your notification preference has been updated.');
    }
}
