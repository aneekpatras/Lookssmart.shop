<?php

namespace App\Channels;

use App\Support\NotificationPreferenceService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class WhatsappChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (NotificationPreferenceService::optedOut($notifiable, 'whatsapp')) {
            return;
        }

        $message = method_exists($notification, 'toWhatsapp') ? $notification->toWhatsapp($notifiable) : '';
        $this->deliver($notifiable, $message);
    }

    protected function deliver($notifiable, string $message): void
    {
        $to = $notifiable->routeNotificationForWhatsapp();

        if (! filled($to) || blank($message)) {
            return;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.auth_token');

        if (! filled($sid) || ! filled($token)) {
            return;
        }

        try {
            Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(15)
                ->post('https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json', [
                    'To' => 'whatsapp:' . preg_replace('/^\+/', '', $to),
                    'From' => config('services.twilio.whatsapp_from'),
                    'Body' => $message,
                ]);
        } catch (\Throwable $e) {
            // Keep the notification pipeline from taking down the booking flow if Twilio fails.
            report($e);
        }
    }
}
