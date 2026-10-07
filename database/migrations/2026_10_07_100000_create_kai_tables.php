<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            // null for cars listed before seller accounts existed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 50);
            $table->decimal('price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 80)->nullable();
            $table->string('country', 60)->nullable();
            // paid placements: weekly push per car; premium is per seller account (users.premium_until)
            $table->timestamp('boosted_until')->nullable();
            // carried over from the old site: per-car premium and contact details of unowned cars
            $table->timestamp('legacy_premium_until')->nullable();
            $table->string('legacy_seller_name', 60)->nullable();
            $table->string('legacy_seller_phone', 25)->nullable();
            $table->string('legacy_seller_email', 120)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at', 'boosted_until']);
        });

        Schema::create('car_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('car_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('car_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_id', 32);
            $table->timestamp('viewed_at')->useCurrent();
            $table->index(['car_id', 'viewed_at']);
            $table->index(['car_id', 'visitor_id']);
        });

        // who has a car's page open right now (refreshed by a ping every 15 s)
        Schema::create('car_watchers', function (Blueprint $table) {
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_id', 32);
            $table->timestamp('last_seen_at')->index();
            $table->primary(['car_id', 'visitor_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_id', 32);
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['visitor_id', 'car_id']);
        });

        Schema::create('car_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_id', 32);
            $table->string('name', 60);
            $table->string('email', 120);
            $table->string('phone', 25);
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index(['car_id', 'visitor_id']);
        });

        Schema::create('car_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('car_id')->index();   // no foreign key: the log outlives the car
            $table->string('action', 10);
            $table->string('old_name', 50)->nullable();
            $table->string('new_name', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['car_logs', 'car_inquiries', 'wishlist_items', 'car_watchers', 'car_views', 'car_parts', 'car_photos', 'cars', 'part_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
