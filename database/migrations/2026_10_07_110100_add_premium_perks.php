<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Premium perks: special deals for premium sellers, a premium plan for buyers,
// saved searches with alerts, and wishlists linked to accounts so alerts can reach their owner.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('buyer_premium_plan', 10)->nullable()->after('premium_until');
            $table->dateTime('buyer_premium_since')->nullable()->after('buyer_premium_plan');
            $table->dateTime('buyer_premium_until')->nullable()->index()->after('buyer_premium_since');
        });

        Schema::create('car_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->decimal('regular_price', 10, 2);
            $table->decimal('deal_price', 10, 2);
            $table->decimal('member_price', 10, 2)->nullable();
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['car_id', 'ends_at']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 80)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('visitor_id')->constrained()->nullOnDelete();
        });

        Schema::table('car_inquiries', function (Blueprint $table) {
            $table->boolean('is_member')->default(false)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('car_inquiries', fn (Blueprint $table) => $table->dropColumn('is_member'));
        Schema::table('wishlist_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('car_deals');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['buyer_premium_plan', 'buyer_premium_since', 'buyer_premium_until']));
    }
};
