<?php

namespace App\Events;

use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;

class BookingRescheduled
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Booking $booking,
        public readonly CarbonInterface $previousStartsAt,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.bookings'),
            new PrivateChannel('staff.bookings'),
        ];
    }
}
