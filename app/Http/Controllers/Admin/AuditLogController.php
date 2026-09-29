<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Phase 5 sub-step 4 — a filterable read-only view over the `activity_log` table populated by
 * `LogsAuditableActivity`/`LogsActivity` since Phase 4. `super-admin` only (Brief §2/§3 module #18:
 * "Audit Log (super-admin only)") — gated via the `audit.view` permission, which only `super-admin`
 * holds in `RolesAndPermissionsSeeder`.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $activities = Activity::query()
            ->with('causer:id,name')
            ->when($request->filled('log_name'), fn ($query) => $query->where('log_name', $request->string('log_name')->value()))
            ->when($request->filled('causer_id'), fn ($query) => $query->where('causer_id', $request->integer('causer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/AuditLog', [
            'activities' => $activities->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'causer_name' => $activity->causer?->name ?? 'System',
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]),
            'filters' => [
                'log_name' => $request->string('log_name')->value() ?: null,
                'causer_id' => $request->integer('causer_id') ?: null,
                'from' => $request->string('from')->value() ?: null,
                'to' => $request->string('to')->value() ?: null,
            ],
            'logNames' => Activity::query()->distinct()->pluck('log_name'),
            'causers' => User::query()->whereHas('roles')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
