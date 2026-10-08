<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\CarPhoto;
use App\Services\PhotoStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

// Puts the photos from a folder into the database, each with the car it is named after:
// "Volvo XC60 B4.jpg" goes to the car named "Volvo XC60 B4"; "bmw2.jpg" to the car whose name is "BMW"
// (case, accents and a trailing number don't matter). A photo the car already has is not added twice.
class ImportPhotos extends Command
{
    protected $signature = 'photos:import {folder=photos-inbox : folder with the photos} {--dry-run : only show what would happen}';

    protected $description = 'Import car photos from a folder into the database, matched to cars by file name';

    public function handle(PhotoStore $store): int
    {
        $folder = base_path($this->argument('folder'));
        $files = glob($folder.'/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE) ?: [];
        if (! $files) {
            $this->warn("No photos in {$folder}.");

            return self::SUCCESS;
        }
        $cars = Car::query()->get(['id', 'name'])->keyBy(fn (Car $c) => $this->key($c->name));
        $rows = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $car = $cars->get($this->key($name)) ?? $cars->get($this->key(preg_replace('/[\s_-]*\d+$/', '', $name)));
            if (! $car) {
                $rows[] = [basename($file), '—', 'skipped: no car with this name'];

                continue;
            }
            if ($this->alreadyHas($car, $file)) {
                $rows[] = [basename($file), "#{$car->id} {$car->name}", 'already there'];

                continue;
            }
            if (! $this->option('dry-run')) {
                $photo = $store->add($car, $file);
                $rows[] = [basename($file), "#{$car->id} {$car->name}", 'imported as photo #'.$photo->id.' ('.round($photo->size / 1024).' KB)'];
            } else {
                $rows[] = [basename($file), "#{$car->id} {$car->name}", 'would be imported'];
            }
        }

        $this->table(['File', 'Car', 'Result'], $rows);

        return self::SUCCESS;
    }

    // "Škoda Octavia" and "skoda  octavia" are the same name
    private function key(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', Str::ascii(\Normalizer::normalize($name, \Normalizer::FORM_C) ?: $name))));
    }

    // Compares a tiny greyscale thumbnail of the file with the car's photos, so a re-saved or rescaled
    // copy of the same picture counts as the same photo
    private function alreadyHas(Car $car, string $file): bool
    {
        $mine = $this->fingerprint(file_get_contents($file));
        foreach (CarPhoto::withoutGlobalScope('without-data')->where('car_id', $car->id)->whereNotNull('data')->pluck('data') as $bytes) {
            $theirs = $this->fingerprint($bytes);
            $diff = array_sum(array_map(fn ($a, $b) => abs($a - $b), $mine, $theirs)) / count($mine);
            if ($diff < 12) {
                return true;
            }
        }

        return false;
    }

    /** @return int[] 16×16 grey levels */
    private function fingerprint(string $bytes): array
    {
        $image = ImageManager::usingDriver(GdDriver::class)->decodeBinary($bytes)->resize(16, 16)->grayscale();
        $grey = [];
        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 16; $x++) {
                $grey[] = $image->colorAt($x, $y)->red()->value();
            }
        }

        return $grey;
    }
}
