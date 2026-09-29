<?php

namespace App\Channels;

use App\Support\NotificationPreferenceService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class SmsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (NotificationPreferenceService::optedOut($notifiable, 'sms')) {
            return;
        }

        $message = method_exists($notification, 'toSms') ? $notification->toSms($notifiable) : '';
        $this->deliver($notifiable, $message);
    }

    protected function deliver($notifiable, string $message): void
    {
        $to = $notifiable->routeNotificationForSms();

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
                    'To' => $to,
                    'From' => config('services.twilio.from_number'),
                    'Body' => $message,
                ]);
        } catch (\Throwable $e) {
            // Fail closed: keep the booking flow resilient and avoid crashing other channels.
            report($e);
        }
    }
}
