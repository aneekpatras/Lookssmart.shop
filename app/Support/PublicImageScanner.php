<?php

namespace App\Support;

use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Scans `public/images/` for real image files, used as the fallback source for the homepage bridal
 * marquee when an admin has not enabled any gallery images for it.
 *
 * The task spec suggested the files would be named `bridal-1.jpg`, `bridal-2.jpg` and so on. They
 * are not — the actual assets on disk are `bride 1.jpg` … `bride 4.jpg`, WITH SPACES. Hardcoding the
 * guessed pattern would have quietly matched nothing and left the slider empty, so this scans the
 * directory instead and works whatever the operator drops in there.
 *
 * Two details that matter:
 *
 * - **Spaces and other unsafe characters are percent-encoded** with `rawurlencode()` applied
 *   per-segment, which is what `encodeURI()` does in the browser. A raw `bride 1.jpg` in an `<img
 *   src>` is not a valid URL and browsers only sometimes recover from it.
 * - **Non-images are excluded by extension allowlist**, not by "everything that isn't an mp4" —
 *   `public/images/` also holds the hero video, and an allowlist stays correct as more asset types
 *   land there.
 */
class PublicImageScanner
{
    /** Extensions a browser will reliably render in an `<img>`. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];

    /**
     * @return list<array{id: string, url: string, alt: string, source: string}>
     */
    public static function bridalFallback(string $directory = 'images'): array
    {
        $absolute = public_path($directory);

        if (! is_dir($absolute)) {
            return [];
        }

        $files = iterator_to_array(
            Finder::create()
                ->files()
                ->in($absolute)
                ->depth(0)
                ->name('/\.(' . implode('|', self::IMAGE_EXTENSIONS) . ')$/i')
                ->sortByName(true), // Natural sort so "bride 2" precedes "bride 10".
            false,
        );

        return array_values(array_map(
            fn (SplFileInfo $file) => [
                // Prefixed so a fallback id can never collide with a real GalleryImage id.
                'id' => 'fallback-' . Str::slug($file->getBasename('.' . $file->getExtension())),
                'url' => self::encodedUrl($directory, $file->getFilename()),
                'alt' => self::altText($file),
                'source' => 'public-folder',
            ],
            $files,
        ));
    }

    /**
     * Encodes each path segment individually — `rawurlencode()` on the whole path would escape the
     * `/` separators too. This mirrors `encodeURI()`'s behaviour of leaving the structure intact
     * while escaping spaces and other unsafe characters inside the segments.
     */
    private static function encodedUrl(string $directory, string $filename): string
    {
        $segments = array_map('rawurlencode', array_filter(explode('/', $directory)));

        return '/' . implode('/', [...$segments, rawurlencode($filename)]);
    }

    /**
     * Turns "bride 1.jpg" into "Bride 1" — a real description of the photo rather than a filename,
     * since the alt text is read aloud by screen readers.
     */
    private static function altText(SplFileInfo $file): string
    {
        $name = $file->getBasename('.' . $file->getExtension());

        return Str::title(trim(preg_replace('/[-_]+/', ' ', $name)));
    }
}
