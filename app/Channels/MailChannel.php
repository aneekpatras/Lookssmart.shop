<?php

namespace App\Channels;

use App\Support\NotificationPreferenceService;
use Illuminate\Notifications\Channels\MailChannel as LaravelMailChannel;
use Illuminate\Notifications\Notification;

class MailChannel
{
    public function __construct(private readonly LaravelMailChannel $mailChannel) {}

    public function send($notifiable, Notification $notification): void
    {
        if (NotificationPreferenceService::optedOut($notifiable, 'mail')) {
            return;
        }

        $this->mailChannel->send($notifiable, $notification);
    }
}
