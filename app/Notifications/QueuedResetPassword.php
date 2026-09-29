<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's built-in `Illuminate\Auth\Notifications\ResetPassword` doesn't implement `ShouldQueue`
 * (framework default, not overridable without subclassing) — sent synchronously it blocks the
 * Forgot Password response on real SMTP negotiation. This is that same notification with queueing
 * added; wired in via `User::sendPasswordResetNotification()`.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
