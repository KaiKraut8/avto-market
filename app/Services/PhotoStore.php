<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

// Saves a car's photos in the database (car_photos.data), scaled down to MAX_SIDE px.
// Every photo is its own row, so two sellers can upload files with the same name without any clash.
class PhotoStore
{
    public const MAX_SIDE = 1600;

    public const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public const MAX_BYTES = 8 * 1024 * 1024;

    /** @param UploadedFile[] $files  @return array{added:int, skipped:int} */
    public function store(Car $car, array $files): array
    {
        $added = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $this->acceptable($file)) {
                $skipped++;

                continue;
            }
            $this->add($car, $file->getRealPath(), $file->getMimeType());
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    // Saves one image file as the car's next photo, in the database; also used by the imports
    public function add(Car $car, string $sourcePath, ?string $mime = null): CarPhoto
    {
        $mime ??= (string) (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        $bytes = $this->encode($sourcePath, self::ALLOWED[$mime] ?? 'jpg');

        return $car->photos()->create([
            'data' => $bytes,
            'mime' => isset(self::ALLOWED[$mime]) ? $mime : 'image/jpeg',
            'size' => strlen($bytes),
            'position' => (int) $car->photos()->max('position') + 1,
        ]);
    }

    // The image as it is stored: scaled down to MAX_SIDE, re-encoded (which also drops any hidden payload)
    public function encode(string $sourcePath, string $ext): string
    {
        if ($ext === 'gif') {   // keep animations as they are
            return (string) file_get_contents($sourcePath);
        }
        $image = ImageManager::usingDriver(GdDriver::class)->decodePath($sourcePath)->scaleDown(self::MAX_SIDE, self::MAX_SIDE);

        return (string) $image->encodeUsingFileExtension($ext, quality: 85);
    }

    // Trust the file contents, not the browser-supplied name or type
    public function acceptable(UploadedFile $file): bool
    {
        return $file->isValid()
            && $file->getSize() <= self::MAX_BYTES
            && isset(self::ALLOWED[$file->getMimeType()])
            && @getimagesize($file->getRealPath()) !== false;
    }

    public function delete(CarPhoto $photo): void
    {
        if ($photo->path) {   // a photo from before the database era: its old file goes too
            Storage::disk('public')->delete($photo->path);
        }
        $photo->delete();
    }
}
