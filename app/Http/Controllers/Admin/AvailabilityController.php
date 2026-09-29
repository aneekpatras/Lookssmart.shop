<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 14 sub-step 2: replaces the `Admin/Availability` placeholder shell. Scoped to per-staff
 * working hours and time-off — the two schedule concepts with no admin UI anywhere in the app yet
 * (both have Policies from Phase 4, but this is their first real controller). Business-wide hours
 * already have a real editor (Settings -> Business Hours, Phase 10), so this page links to that
 * rather than building a second UI onto the same `BusinessHour` table.
 */
class AvailabilityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StaffWorkingHour::class);

        $staff = Staff::query()
            ->active()
            ->with(['user:id,name', 'workingHours', 'timeOff' => fn ($query) => $query->where('ends_at', '>=', now())->orderBy('starts_at')])
            ->get()
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->user?->name ?? 'Unassigned',
                'working_hours' => $member->workingHours->map(fn (StaffWorkingHour $hour) => [
                    'id' => $hour->id,
                    'weekday' => $hour->weekday,
                    'start_time' => $hour->start_time,
                    'end_time' => $hour->end_time,
                ]),
                'time_off' => $member->timeOff->map(fn (StaffTimeOff $timeOff) => [
                    'id' => $timeOff->id,
                    'starts_at' => $timeOff->starts_at?->toIso8601String(),
                    'ends_at' => $timeOff->ends_at?->toIso8601String(),
                    'reason' => $timeOff->reason,
                ]),
            ]);

        return Inertia::render('Admin/Availability', [
            'staff' => $staff,
        ]);
    }

    public function storeWorkingHour(Request $request): RedirectResponse
    {
        $this->authorize('create', StaffWorkingHour::class);

        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        StaffWorkingHour::updateOrCreate(
            ['staff_id' => $data['staff_id'], 'weekday' => $data['weekday']],
            ['start_time' => $data['start_time'], 'end_time' => $data['end_time']],
        );

        return back()->with('success', 'Working hours saved.');
    }

    public function destroyWorkingHour(StaffWorkingHour $workingHour): RedirectResponse
    {
        $this->authorize('delete', $workingHour);

        $workingHour->delete();

        return back()->with('success', 'Working hours removed.');
    }

    public function storeTimeOff(Request $request): RedirectResponse
    {
        $this->authorize('create', StaffTimeOff::class);

        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // StaffTimeOffPolicy::create() intentionally allows any staff member through (a future
        // self-service "request my own time off" flow) — it has no model instance to check
        // `staff_id` against yet, so this admin-wide page must enforce ownership itself: without
        // `staff.manage`, a staff member may only ever add time off against their own record.
        if (! $request->user()->can('staff.manage') && $request->user()->staff?->id !== (int) $data['staff_id']) {
            abort(403, 'You may only add time off for yourself.');
        }

        StaffTimeOff::create($data);

        return back()->with('success', 'Time off added.');
    }

    public function destroyTimeOff(StaffTimeOff $timeOff): RedirectResponse
    {
        $this->authorize('delete', $timeOff);

        $timeOff->delete();

        return back()->with('success', 'Time off removed.');
    }
}
