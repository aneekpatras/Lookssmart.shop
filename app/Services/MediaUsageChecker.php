<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\Post;
use App\Models\Slide;
use Illuminate\Support\Collection;

/**
 * Phase 10 sub-step 5: live usage lookup for the Media Library's delete-safety check. Deliberately
 * queries each consumer table for the given path(s) at call time rather than keeping a stored
 * reference table in sync — a live query can never go stale (e.g. after a cover image is swapped
 * elsewhere), where a cached reference could.
 *
 * Each usage entry's `clearable` flag says whether deleting the asset can safely null out that
 * reference: required, non-nullable columns (a slide's or gallery image's primary `image_path`) are
 * NOT clearable — force-deleting those would leave the row in an invalid state — while optional
 * references (a slide's mobile image, a gallery image's before/after pair, a business logo) are.
 */
class MediaUsageChecker
{
    /**
     * Batched form of usagesFor(), for a whole page of assets at once: one query per consumer
     * table (~7 total) instead of one full usagesFor() sweep (~7-9 queries) PER asset. The Media
     * Library index paginates 24 assets/page — before this, rendering that one page ran roughly
     * 170-200 queries (24 × ~7-9), all to answer the same handful of "which paths are referenced
     * anywhere" questions that a single `whereIn()` per table answers just as completely.
     *
     * @param string[] $paths
     * @return array<string, array<int, array{label: string, clearable: bool, clear: callable}>> keyed by path
     */
    public function usagesForMany(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths)));

        /** @var array<string, array<int, array{label: string, clearable: bool, clear: callable}>> $usages */
        $usages = array_fill_keys($paths, []);

        if ($paths === []) {
            return $usages;
        }

        Post::whereIn('cover_image_path', $paths)->get()->each(function (Post $post) use (&$usages) {
            $usages[$post->cover_image_path][] = [
                'label' => "Blog post cover: \"{$post->title}\"",
                'clearable' => true,
                'clear' => fn () => $post->update(['cover_image_path' => null]),
            ];
        });

        Slide::whereIn('image_path', $paths)->get()->each(function (Slide $slide) use (&$usages) {
            $usages[$slide->image_path][] = [
                'label' => "Hero slide primary image: \"{$slide->heading}\"",
                'clearable' => false,
                'clear' => fn () => null,
            ];
        });

        Slide::whereIn('mobile_image_path', $paths)->get()->each(function (Slide $slide) use (&$usages) {
            $usages[$slide->mobile_image_path][] = [
                'label' => "Hero slide mobile image: \"{$slide->heading}\"",
                'clearable' => true,
                'clear' => fn () => $slide->update(['mobile_image_path' => null]),
            ];
        });

        GalleryImage::with('gallery')->whereIn('image_path', $paths)->get()->each(function (GalleryImage $image) use (&$usages) {
            $usages[$image->image_path][] = [
                'label' => "Gallery photo in album: \"{$image->gallery?->title}\"",
                'clearable' => false,
                'clear' => fn () => null,
            ];
        });

        GalleryImage::with('gallery')->whereIn('pair_image_path', $paths)->get()->each(function (GalleryImage $image) use (&$usages) {
            $usages[$image->pair_image_path][] = [
                'label' => "Gallery before/after pair in album: \"{$image->gallery?->title}\"",
                'clearable' => true,
                'clear' => fn () => $image->update(['pair_image_path' => null]),
            ];
        });

        /** @var Collection<int, GalleryImage> $matchingImages */
        $matchingImages = GalleryImage::whereIn('image_path', $paths)->get(['id', 'image_path'])->keyBy('id');

        if ($matchingImages->isNotEmpty()) {
            Gallery::whereIn('cover_image_id', $matchingImages->keys())->get()->each(function (Gallery $gallery) use (&$usages, $matchingImages) {
                $path = $matchingImages->get($gallery->cover_image_id)?->image_path;

                if ($path !== null) {
                    $usages[$path][] = [
                        'label' => "Cover image for album: \"{$gallery->title}\"",
                        'clearable' => true,
                        'clear' => fn () => $gallery->update(['cover_image_id' => null]),
                    ];
                }
            });
        }

        $settingService = app(SettingService::class);
        foreach (['business.logo_path' => 'Business logo', 'business.favicon_path' => 'Business favicon'] as $key => $label) {
            $value = $settingService->get($key);

            if ($value !== null && isset($usages[$value])) {
                $usages[$value][] = [
                    'label' => $label,
                    'clearable' => true,
                    'clear' => fn () => $settingService->set($key, null, 'business'),
                ];
            }
        }

        return $usages;
    }

    /** @return array<int, array{label: string, clearable: bool, clear: callable}> */
    public function usagesFor(string $path): array
    {
        return $this->usagesForMany([$path])[$path] ?? [];
    }

    public function isInUse(string $path): bool
    {
        return $this->usagesFor($path) !== [];
    }
}
