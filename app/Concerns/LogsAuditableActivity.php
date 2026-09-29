<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Activity;

/**
 * Brief §5 / Phase 4 item 8: every model that uses spatie/laravel-activitylog's `LogsActivity` trait
 * also uses this one, so the actor (spatie captures the authenticated `causer` automatically),
 * IP, and user agent are attached consistently rather than each model remembering to do it itself.
 * The before/after diff comes from `LogsActivity` + `logOnlyDirty()` in each model's own
 * `getActivitylogOptions()` — this trait only adds the request-context properties.
 */
trait LogsAuditableActivity
{
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
