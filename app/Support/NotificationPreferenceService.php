<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class NotificationPreferenceService
{
    public static function optedOut(mixed $notifiable, string $channel): bool
    {
        $profile = $notifiable instanceof User ? $notifiable->customerProfile : null;

        return match ($channel) {
            'mail' => (bool) $profile?->email_opt_out,
            'sms' => (bool) $profile?->sms_opt_out,
            'whatsapp' => (bool) $profile?->whatsapp_opt_out,
            default => false,
        };
    }

    public static function unsubscribeUrl(User $user, string $channel): string
    {
        return URL::temporarySignedRoute(
            'notifications.unsubscribe',
            now()->addDays(30),
            ['user' => $user->getKey(), 'channel' => $channel],
        );
    }
}
