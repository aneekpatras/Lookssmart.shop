<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\User;
use App\Support\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brief §4 / Phase 8 items 2-3: "birthday offer" — template only this sub-step; the trigger (a
 * `birthday` `ReminderRule` matching customers whose `CustomerProfile.dob` is today/upcoming) is
 * sub-step 2's job.
 */
class BirthdayOffer extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return array_values(array_filter([
            MailChannel::class,
            'database',
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? SmsChannel::class : null,
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? WhatsappChannel::class : null,
        ]));
    }

    public function toSms(mixed $notifiable): string
    {
        return 'Happy birthday! Enjoy 10% off your next visit with code BIRTHDAY10. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
    }

    public function toWhatsapp(mixed $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : 'there';

        return (new MailMessage)
            ->subject('Happy Birthday from Looks Smart Beauty Salon! 🎉')
            ->view('mail.birthday-offer', [
                'customer_name' => $name,
                'offer_code' => 'BIRTHDAY10',
                'cta_url' => url('/book'),
                'cta_label' => 'Book now',
                'title' => 'Happy Birthday!',
                'subtitle' => 'A little gift from us to celebrate your special day.',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => 'Happy birthday! Enjoy a special offer this month.',
        ];
    }
}
