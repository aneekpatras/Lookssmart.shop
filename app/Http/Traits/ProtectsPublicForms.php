<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

/**
 * Brief §5 anti-abuse for public forms (contact/booking/review, wired up as those routes land in
 * Phases 7/9/11): a hidden honeypot field real users never fill in, and a minimum elapsed time
 * between the form rendering and its submission — bots typically submit near-instantly.
 */
trait ProtectsPublicForms
{
    protected function passesHoneypotAndTimeTrap(
        Request $request,
        string $honeypotField = 'website',
        string $renderedAtField = 'rendered_at',
        int $minSeconds = 3,
    ): bool {
        if (filled($request->input($honeypotField))) {
            return false;
        }

        $renderedAt = (int) $request->input($renderedAtField, 0);

        if ($renderedAt <= 0) {
            return false;
        }

        return (time() - $renderedAt) >= $minSeconds;
    }
}
