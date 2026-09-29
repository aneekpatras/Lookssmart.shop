<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaAssetsRequest;
use App\Models\MediaAsset;
use App\Services\MediaUsageChecker;
use App\Services\SecureUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaLibraryController extends Controller
{
    public function index(Request $request, MediaUsageChecker $usageChecker): Response
    {
        $this->authorize('viewAny', MediaAsset::class);

        $assets = MediaAsset::query()
            ->with('uploader:id,name')
            ->search($request->string('search')->value() ?: null)
            ->ofType($request->string('type')->value() ?: null)
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        // One batched lookup for the whole page (~7 queries total) instead of a full usagesFor()
        // sweep per asset (~7-9 queries × 24 rows) — see MediaUsageChecker::usagesForMany().
        $usagesByPath = $usageChecker->usagesForMany($assets->pluck('path')->all());

        return Inertia::render('Admin/CMS/Media/Index', [
            'assets' => $assets->through(fn (MediaAsset $asset) => [
                'id' => $asset->id,
                'path' => $asset->path,
                'url' => asset("storage/{$asset->path}"),
                'original_name' => $asset->original_name,
                'mime_type' => $asset->mime_type,
                'size' => $asset->size,
                'uploader' => $asset->uploader?->name,
                'created_at' => $asset->created_at?->toIso8601String(),
                'usages' => collect($usagesByPath[$asset->path] ?? [])
                    ->map(fn (array $usage) => ['label' => $usage['label'], 'clearable' => $usage['clearable']])
                    ->values(),
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'type' => $request->string('type')->value() ?: null,
                'from' => $request->string('from')->value() ?: null,
                'to' => $request->string('to')->value() ?: null,
            ],
        ]);
    }

    public function store(StoreMediaAssetsRequest $request, SecureUploadService $uploads): RedirectResponse
    {
        foreach ($request->file('files') as $file) {
            $uploads->storePublicImage($file, 'media');
        }

        return back()->with('success', 'Files uploaded.');
    }

    /**
     * Always returns JSON, not an Inertia redirect — the admin UI calls this directly via axios
     * (not Inertia's router) so it can read the usage list and real HTTP status code for the
     * delete-confirmation flow, regardless of the request's Accept header.
     */
    public function destroy(MediaAsset $mediaAsset, Request $request, MediaUsageChecker $usageChecker, SecureUploadService $uploads): JsonResponse
    {
        $this->authorize('delete', $mediaAsset);

        $usages = $usageChecker->usagesFor($mediaAsset->path);
        $force = $request->boolean('force');

        if ($usages !== [] && ! $force) {
            return response()->json([
                'message' => 'This file is currently in use and cannot be deleted without confirmation.',
                'usages' => array_map(fn (array $usage) => ['label' => $usage['label'], 'clearable' => $usage['clearable']], $usages),
            ], 422);
        }

        if ($usages !== [] && $force) {
            $blocking = array_filter($usages, fn (array $usage) => ! $usage['clearable']);

            if ($blocking !== []) {
                return response()->json([
                    'message' => 'This file is required by ' . implode(', ', array_column($blocking, 'label')) . ' and cannot be deleted.',
                ], 409);
            }

            foreach ($usages as $usage) {
                ($usage['clear'])();
            }
        }

        $uploads->deletePublic($mediaAsset->path);

        return response()->json(['message' => 'File deleted.']);
    }
}
