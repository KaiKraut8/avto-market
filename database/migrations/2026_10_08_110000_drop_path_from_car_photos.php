<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Photos are only in car_photos.data now; the path of the old files is no longer needed
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_photos', function (Blueprint $table) {
            $table->dropColumn('path');
        });
    }

    public function down(): void
    {
        Schema::table('car_photos', function (Blueprint $table) {
            $table->string('path')->nullable()->after('car_id');
        });
    }
};
