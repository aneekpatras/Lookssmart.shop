<?php

namespace App\Models;

use App\Concerns\LogsAuditableActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use LogsActivity, LogsAuditableActivity;

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_encrypted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget("setting:{$setting->key}"));
        static::deleted(fn (Setting $setting) => Cache::forget("setting:{$setting->key}"));
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Cached (5 min — real Redis-backed since Phase 6/Decision #29) read for the availability engine
     * and other hot paths that would otherwise hit `settings` on every request. Invalidated
     * immediately on save/delete via the model events above, so a stale value never outlives an edit.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Deliberately loads the whole model (not ->value('value')) so Eloquent's `array` cast on
        // `value` actually runs — a raw query-builder ->value() would return the undecoded JSON string.
        return Cache::remember("setting:{$key}", 300, fn () => static::where('key', $key)->first()?->value ?? $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value', 'group'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('settings');
    }
}
