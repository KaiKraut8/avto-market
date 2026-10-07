<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Real payments: premium subscriptions that renew until cancelled, and one-off payments (push forward).
// users.premium_until / buyer_premium_until stay the switch the rest of the site reads; billing moves them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                 // seller | buyer
            $table->string('plan', 10);                 // monthly | yearly
            $table->string('method', 20);               // creditcard | paypal | paysafecard
            $table->string('status', 12)->default('pending')->index();   // pending | active | past_due | ended
            $table->dateTime('current_period_end')->nullable()->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->dateTime('canceled_at')->nullable();
            $table->unsignedTinyInteger('failed_renewals')->default(0);
            $table->dateTime('reminded_at')->nullable();   // paysafecard: "pay for the next period" was sent
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_mandate_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20);              // subscription | renewal | boost
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('method', 20);
            $table->string('description');
            $table->string('status', 10)->default('open')->index();   // open | paid | failed | canceled | expired
            $table->string('provider', 10);             // mollie | test
            $table->string('provider_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('applied_at')->nullable(); // what was paid for was handed out (exactly once)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
    }
};
