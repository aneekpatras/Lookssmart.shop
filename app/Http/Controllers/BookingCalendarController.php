<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\IcsGenerator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingCalendarController extends Controller
{
    public function ics(Booking $booking): StreamedResponse
    {
        abort_if(in_array($booking->status, ['cancelled', 'no_show'], true), 410);

        $booking->loadMissing(['items.service', 'staff.user']);
        $filename = 'looks-smart-booking-' . $booking->code . '.ics';
        $ics = IcsGenerator::forBooking($booking);

        return response()->streamDownload(
            static function () use ($ics): void {
                echo $ics;
            },
            $filename,
            ['Content-Type' => 'text/calendar; charset=UTF-8'],
        );
    }
}
