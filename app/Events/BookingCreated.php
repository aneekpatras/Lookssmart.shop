<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;

class BookingCreated
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly Booking $booking) {}

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
