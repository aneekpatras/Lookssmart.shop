<?php

use App\Services\SecureUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Brief §5 / Phase 4 item 11: "uploading a .php file renamed to .jpg" — this is the exact scenario
 * the whole SecureUploadService exists to defeat. Unit-level only per the approved Phase 4 plan: a
 * real HTTP endpoint test waits until Phase 6/10 actually adds an upload route to test against —
 * faking one now would exercise nothing real.
 */
it('rejects a php file renamed to .jpg', function () {
    $maliciousPath = tempnam(sys_get_temp_dir(), 'malicious');
    file_put_contents($maliciousPath, "<?php echo 'this is not an image'; ?>");

    // $test = true makes this a valid "uploaded" file in Laravel's eyes without a real HTTP request;
    // getMimeType() still sniffs the ACTUAL bytes on disk via fileinfo, ignoring the claimed type.
    $file = new UploadedFile($maliciousPath, 'shell.jpg', 'image/jpeg', null, true);

    $service = app(SecureUploadService::class);

    try {
        expect(fn () => $service->storeImage($file))->toThrow(ValidationException::class);
    } finally {
        @unlink($maliciousPath);
    }
});

it('rejects a text file with a spoofed image mime and non-image extension', function () {
    $path = tempnam(sys_get_temp_dir(), 'malicious');
    file_put_contents($path, str_repeat('not an image at all. ', 50));

    $file = new UploadedFile($path, 'note.jpg', 'image/png', null, true);

    $service = app(SecureUploadService::class);

    try {
        expect(fn () => $service->storeImage($file))->toThrow(ValidationException::class);
    } finally {
        @unlink($path);
    }
});

it('stores and re-encodes a genuine image on the private local disk', function () {
    Storage::fake('local');

    $manager = new ImageManager(new Driver);
    $canvas = $manager->create(20, 20)->fill('ff0000');

    $sourcePath = tempnam(sys_get_temp_dir(), 'realimg') . '.jpg';
    file_put_contents($sourcePath, (string) $canvas->toJpeg());

    $file = new UploadedFile($sourcePath, 'photo.jpg', 'image/jpeg', null, true);

    $service = app(SecureUploadService::class);

    try {
        $storedPath = $service->storeImage($file);

        expect($storedPath)->toStartWith('uploads/images/')
            ->and($storedPath)->toEndWith('.jpg')
            ->and(Storage::disk('local')->exists($storedPath))->toBeTrue();

        // The re-encoded bytes are NOT a byte-for-byte copy of the source — confirms this went
        // through Intervention's decode/encode round-trip rather than a plain file copy.
        $stored = Storage::disk('local')->get($storedPath);
        expect($stored)->not->toBe(file_get_contents($sourcePath));
    } finally {
        @unlink($sourcePath);
    }
});

it('rejects an image over the size cap', function () {
    Storage::fake('local');

    $manager = new ImageManager(new Driver);
    $canvas = $manager->create(20, 20)->fill('00ff00');

    $sourcePath = tempnam(sys_get_temp_dir(), 'realimg') . '.png';
    file_put_contents($sourcePath, (string) $canvas->toPng());

    // UploadedFile::getSize() reads from the real file on disk for test-mode instances, so mock a
    // subclass instead of trying to inflate an actual multi-megabyte temp file just for this check.
    $file = new class($sourcePath, 'huge.png', 'image/png', null, true) extends UploadedFile
    {
        public function getSize(): int|false
        {
            return 6 * 1024 * 1024;
        }
    };

    $service = app(SecureUploadService::class);

    try {
        expect(fn () => $service->storeImage($file))->toThrow(ValidationException::class);
    } finally {
        @unlink($sourcePath);
    }
});
