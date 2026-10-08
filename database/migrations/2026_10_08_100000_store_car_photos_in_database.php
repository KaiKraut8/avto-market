<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// Car photos live in the database (a MEDIUMBLOB, up to 16 MB) instead of files in storage/app/public.
// The photos already on disk are copied in; the files themselves are left where they are.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_photos', function (Blueprint $table) {
            $table->string('path')->nullable()->change();   // only kept for photos that came from files
            $table->string('mime', 30)->nullable()->after('path');
            $table->unsignedInteger('size')->nullable()->after('mime');
        });
        DB::statement('ALTER TABLE car_photos ADD data MEDIUMBLOB NULL AFTER size');

        $disk = Storage::disk('public');
        foreach (DB::table('car_photos')->whereNull('data')->whereNotNull('path')->select('id', 'path')->get() as $photo) {
            if (! $disk->exists($photo->path)) {
                continue;
            }
            $bytes = $disk->get($photo->path);
            DB::table('car_photos')->where('id', $photo->id)->update([
                'data' => $bytes,
                'mime' => (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg',
                'size' => strlen($bytes),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('car_photos', function (Blueprint $table) {
            $table->dropColumn(['data', 'mime', 'size']);
        });
    }
};
