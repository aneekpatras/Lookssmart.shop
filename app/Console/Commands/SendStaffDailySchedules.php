<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Notifications\StaffDailySchedule;
use Illuminate\Console\Command;

/**
 * Brief §4 / Phase 8 item 2: sends each active staff member their day's bookings every morning.
 * Self-contained (not part of the sub-step 2 `ReminderRule` engine, which only covers
 * customer-facing events) — scheduled directly in `routes/console.php`.
 */
class SendStaffDailySchedules extends Command
{
    protected $signature = 'app:send-staff-daily-schedules';

    protected $description = "Notify each active staff member of today's bookings";

    public function handle(): int
    {
        $today = now()->startOfDay();
        $tomorrow = $today->clone()->addDay();

        $staffMembers = Staff::active()->with('user')->get();

        foreach ($staffMembers as $staff) {
            $bookings = $staff->bookings()
                ->with(['items.service', 'customer'])
                ->whereBetween('starts_at', [$today, $tomorrow])
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->orderBy('starts_at')
                ->get();

            $staff->user?->notify(new StaffDailySchedule($bookings));
        }

        $this->info("Sent daily schedules to {$staffMembers->count()} staff member(s).");

        return self::SUCCESS;
    }
}
