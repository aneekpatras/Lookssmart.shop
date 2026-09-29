<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessHour;
use App\Models\Setting;
use App\Services\SecureUploadService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const GROUPS = ['business', 'booking', 'payments', 'integrations', 'seo', 'security'];

    public function index(): Response
    {
        $this->authorize('viewAny', Setting::class);

        $settingService = app(SettingService::class);

        return Inertia::render('Admin/CMS/Settings/Index', [
            'settings' => collect(self::GROUPS)->mapWithKeys(
                fn (string $group) => [$group => $settingService->maskedGroup($group)],
            ),
            'businessHours' => BusinessHour::orderBy('weekday')->get(['weekday', 'open_time', 'close_time', 'is_closed']),
        ]);
    }

    public function updateGroup(Request $request, string $group, SettingService $settingService): RedirectResponse
    {
        $this->authorize('create', Setting::class);

        abort_unless(in_array($group, self::GROUPS, true), 404);

        $data = $request->validate(['settings' => ['required', 'array']]);
        $this->validateGroupPayload($group, $data['settings']);

        $written = $settingService->updateGroup($group, $data['settings']);

        return back()->with('success', count($written) . ' setting(s) saved. Cache cleared.');
    }

    /**
     * Real, per-key validation against SettingService::SCHEMA — done by hand rather than as Laravel
     * validation rules, because a setting key like `business.name` already contains literal dots, and
     * Laravel's validator treats a dotted rule key (`settings.business.name`) as a NESTED array path
     * (settings -> business -> name), not a flat key containing dots. That collision would silently
     * validate against the wrong (nonexistent) input shape and pass through an empty payload. Throwing
     * a manually-built `ValidationException` sidesteps it entirely while still producing the same
     * `settings.{key}` session-error keys the admin UI expects.
     */
    private function validateGroupPayload(string $group, array $values): void
    {
        $errors = [];

        foreach ($values as $key => $value) {
            $meta = SettingService::SCHEMA[$key] ?? null;

            if (! $meta || $meta['group'] !== $group) {
                $errors["settings.{$key}"] = ["Unknown setting key: {$key}"];

                continue;
            }

            $blank = $value === null || $value === '';

            $valid = match ($meta['type']) {
                'int' => $blank || filter_var($value, FILTER_VALIDATE_INT) !== false,
                'float' => $blank || is_numeric($value),
                'bool' => true,
                default => is_scalar($value) && mb_strlen((string) $value) <= 2048,
            };

            if ($valid && $key === 'business.email' && ! $blank && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $valid = false;
            }

            // These 2 get embedded directly into an inline <script> template literal (SeoHead.tsx) —
            // format-validating them here isn't just data hygiene, it keeps a malformed value (e.g. a
            // stray quote) from ever being able to break out of that string, on top of catching a
            // simple admin typo before it ships to every public page.
            if ($valid && $key === 'integrations.google_analytics_id' && ! $blank && ! preg_match('/^G-[A-Z0-9]{6,}$/', $value)) {
                $valid = false;
            }

            if ($valid && $key === 'integrations.google_tag_manager_id' && ! $blank && ! preg_match('/^GTM-[A-Z0-9]{5,}$/', $value)) {
                $valid = false;
            }

            if (! $valid) {
                $errors["settings.{$key}"] = ["The {$key} field is not a valid {$meta['type']}."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function updateHours(Request $request): RedirectResponse
    {
        $this->authorize('create', Setting::class);

        $data = $request->validate([
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'hours.*.is_closed' => ['boolean'],
            'hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'hours.*.close_time' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($data['hours'] as $row) {
            $isClosed = $row['is_closed'] ?? false;

            BusinessHour::updateOrCreate(
                ['weekday' => $row['weekday']],
                [
                    'is_closed' => $isClosed,
                    'open_time' => $isClosed ? null : ($row['open_time'] ?? null),
                    'close_time' => $isClosed ? null : ($row['close_time'] ?? null),
                ],
            );
        }

        // The public footer renders a cached summary of these rows on every page
        // (HandleInertiaRequests::openingHoursSummary) — without this the site would keep
        // advertising the old hours for up to 5 minutes after an admin corrects them.
        Cache::forget('business:hours-summary');

        return back()->with('success', 'Operating hours saved.');
    }

    public function uploadLogo(Request $request, SecureUploadService $uploads, SettingService $settingService): RedirectResponse
    {
        $this->authorize('create', Setting::class);

        return $this->uploadBusinessImage($request, $uploads, $settingService, 'logo', 'business.logo_path');
    }

    public function uploadFavicon(Request $request, SecureUploadService $uploads, SettingService $settingService): RedirectResponse
    {
        $this->authorize('create', Setting::class);

        return $this->uploadBusinessImage($request, $uploads, $settingService, 'favicon', 'business.favicon_path');
    }

    private function uploadBusinessImage(Request $request, SecureUploadService $uploads, SettingService $settingService, string $field, string $key): RedirectResponse
    {
        $request->validate([
            $field => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
        ]);

        $oldPath = $settingService->get($key);
        $path = $uploads->storePublicImage($request->file($field), 'business');
        $settingService->set($key, $path, 'business');
        $uploads->deletePublic(is_string($oldPath) ? $oldPath : null);

        return back()->with('success', ucfirst($field) . ' updated.');
    }
}
