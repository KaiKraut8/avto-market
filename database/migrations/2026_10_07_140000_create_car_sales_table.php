<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every sale earns the marketplace a commission (config pricing.commission_rate, 5 %):
//  - bought on the site: the buyer pays the commission online, which reserves the car; the rest goes to the seller at handover
//  - sold elsewhere: the seller marks the car sold and pays the commission
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('via', 10);                  // site | offline
            $table->string('status', 16)->index();      // pending | reserved | completed | canceled | due | paid
            $table->decimal('price', 10, 2);            // the whole price of the car
            $table->decimal('rate', 5, 2);              // commission in percent, as it was at the time
            $table->decimal('commission', 10, 2);
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dateTime('sold_at')->nullable()->index()->after('legacy_seller_email');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('car_sale_id')->nullable()->after('car_id')->constrained()->nullOnDelete();
            $table->dateTime('refunded_at')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('car_sale_id');
            $table->dropColumn('refunded_at');
        });
        Schema::table('cars', fn (Blueprint $table) => $table->dropColumn('sold_at'));
        Schema::dropIfExists('car_sales');
    }
};
