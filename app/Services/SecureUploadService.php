<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Brief §5 / Phase 4 item 6: validate → sniff MIME → re-encode image → strip EXIF → private disk →
 * signed temporary URL. Everything is stored on the `local` disk (`config/filesystems.php` — root
 * outside `public/`, `serve: true`), whose signed-URL serving is Laravel's own built-in mechanism
 * (`FilesystemManager::createLocalDriver()` registers a `temporaryUrlCallback` when `serve` is on) —
 * there is no world-readable path to a stored file, only a time-limited signed one.
 */
class SecureUploadService
{
    /**
     * Server-sniffed MIME type (never the client-supplied one) → the extension we store it under.
     * The extension in the stored filename is always OUR choice, derived from the sniffed MIME, not
     * whatever the client named the file — this alone defeats a bare "rename .php to .jpg" attack.
     */
    private const IMAGE_MIME_ALLOWLIST = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const DOCUMENT_MIME_ALLOWLIST = [
        'application/pdf' => 'pdf',
    ];

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly ImageManager $images) {}

    /**
     * Decodes the file as an image and re-encodes it from scratch before storing it. This is the
     * real defense, not the MIME/extension checks: a file that merely *claims* to be an image (e.g.
     * PHP source renamed to `.jpg`, or a JPEG with a PHP payload appended after the image data)
     * fails here even if it slipped past the allowlist somehow, because Intervention's decoder
     * throws on anything that isn't genuinely decodable image data, and its encoder writes fresh
     * pixel data only — it never carries the source file's bytes (or EXIF metadata) forward.
     */
    public function storeImage(UploadedFile $file, string $directory = 'uploads/images'): string
    {
        $mime = $file->getMimeType();

        if ($mime === null || ! array_key_exists($mime, self::IMAGE_MIME_ALLOWLIST)) {
            throw ValidationException::withMessages(['file' => 'File is not a supported image type.']);
        }

        if ($file->getSize() > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages(['file' => 'Image exceeds the maximum allowed size.']);
        }

        try {
            $image = $this->images->read($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File could not be decoded as a valid image.']);
        }

        $extension = self::IMAGE_MIME_ALLOWLIST[$mime];

        $encoded = match ($extension) {
            'jpg' => $image->toJpeg(quality: 85),
            'png' => $image->toPng(),
            'webp' => $image->toWebp(quality: 85),
            'gif' => $image->toGif(),
        };

        $path = trim($directory, '/') . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('local')->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Same MIME-sniff/size-cap/decode-and-re-encode defense as storeImage(), but written to the
     * `public` disk instead of the private `local` one — for content that's genuinely meant to be
     * publicly and permanently servable (e.g. CMS slider/gallery images shown on the marketing site),
     * where a signed, expiring temporaryUrl() would be the wrong tool.
     *
     * Phase 13 sub-step 2: also writes a `.webp` sibling next to every non-webp upload (the image is
     * already decoded in memory from the re-encode step above, so this is one extra `toWebp()` call,
     * not a second decode) — `webpSiblingPath()` lets a consumer build a real `<picture>` fallback
     * without a separate "has webp" flag to keep in sync. Not tracked as its own `MediaAsset` row,
     * matching how spatie/medialibrary's own derived 'thumb' conversions (Service/ServiceCategory)
     * aren't catalogued separately either — it's a generated artifact of the original, not a distinct
     * upload.
     */
    public function storePublicImage(UploadedFile $file, string $directory = 'uploads/images'): string
    {
        $mime = $file->getMimeType();

        if ($mime === null || ! array_key_exists($mime, self::IMAGE_MIME_ALLOWLIST)) {
            throw ValidationException::withMessages(['file' => 'File is not a supported image type.']);
        }

        if ($file->getSize() > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages(['file' => 'Image exceeds the maximum allowed size.']);
        }

        try {
            $image = $this->images->read($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File could not be decoded as a valid image.']);
        }

        $extension = self::IMAGE_MIME_ALLOWLIST[$mime];

        $encoded = match ($extension) {
            'jpg' => $image->toJpeg(quality: 85),
            'png' => $image->toPng(),
            'webp' => $image->toWebp(quality: 85),
            'gif' => $image->toGif(),
        };

        $path = trim($directory, '/') . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('public')->put($path, (string) $encoded);

        if ($extension !== 'webp') {
            Storage::disk('public')->put(self::webpSiblingPath($path), (string) $image->toWebp(quality: 85));
        }

        MediaAsset::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => Storage::disk('public')->size($path),
            'uploaded_by' => Auth::id(),
        ]);

        return $path;
    }

    /**
     * The `.webp` sibling path `storePublicImage()` writes alongside a non-webp upload. A consumer
     * should check `Storage::disk('public')->exists(...)` before trusting it — uploads made before
     * this sub-step (or an upload that was already webp) have no sibling.
     */
    public static function webpSiblingPath(string $path): string
    {
        return preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $path);
    }

    /**
     * Non-image documents have no safe "re-encode" step the way pixel data does, so they're
     * validated (real MIME sniff + size cap) and stored under a quarantine prefix on the same
     * private disk — isolated by convention from anything a controller might later serve more
     * liberally, and never auto-executed since the disk root sits outside the public webroot.
     */
    public function storeDocument(UploadedFile $file, string $directory = 'uploads/quarantine'): string
    {
        $mime = $file->getMimeType();

        if ($mime === null || ! array_key_exists($mime, self::DOCUMENT_MIME_ALLOWLIST)) {
            throw ValidationException::withMessages(['file' => 'File is not a supported document type.']);
        }

        if ($file->getSize() > self::MAX_DOCUMENT_BYTES) {
            throw ValidationException::withMessages(['file' => 'Document exceeds the maximum allowed size.']);
        }

        $extension = self::DOCUMENT_MIME_ALLOWLIST[$mime];
        $path = trim($directory, '/') . '/' . Str::random(40) . '.' . $extension;

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Deletes one or more public-disk paths (as returned by storePublicImage()) and their matching
     * MediaAsset catalog rows together, so the Media Library never lists a file that no longer exists
     * on disk. Every call site that removes a public upload should go through this rather than calling
     * Storage::delete() directly.
     *
     * @param string|array<int, string|null>|null $paths
     */
    public function deletePublic(string|array|null $paths): void
    {
        $paths = array_values(array_filter((array) $paths));

        if ($paths === []) {
            return;
        }

        $webpSiblings = array_map(self::webpSiblingPath(...), $paths);

        Storage::disk('public')->delete([...$paths, ...$webpSiblings]);
        MediaAsset::where('disk', 'public')->whereIn('path', $paths)->delete();
    }

    /**
     * A short-lived signed URL for anything stored via storeImage()/storeDocument().
     */
    public function temporaryUrl(string $path, int $minutes = 30): string
    {
        return Storage::disk('local')->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
