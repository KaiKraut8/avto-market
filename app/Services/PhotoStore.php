<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

// Saves a car's photos on the public disk at cars/{car}/{random}.{ext}, scaled down to MAX_SIDE px.
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
        $position = (int) $car->photos()->max('position');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $this->acceptable($file)) {
                $skipped++;

                continue;
            }
            $ext = self::ALLOWED[$file->getMimeType()];
            $path = "cars/{$car->id}/".Str::random(32).".{$ext}";
            $this->put($path, $file->getRealPath(), $ext);

            CarPhoto::create(['car_id' => $car->id, 'path' => $path, 'position' => ++$position]);
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    // Also used by the legacy import, which hands over plain files
    public function put(string $path, string $sourcePath, string $ext): void
    {
        $disk = Storage::disk('public');
        if ($ext === 'gif') {   // keep animations as they are
            $disk->put($path, file_get_contents($sourcePath));

            return;
        }
        $image = ImageManager::usingDriver(GdDriver::class)->decodePath($sourcePath)->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
        $disk->put($path, (string) $image->encodeUsingFileExtension($ext, quality: 85));
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
        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }
}
