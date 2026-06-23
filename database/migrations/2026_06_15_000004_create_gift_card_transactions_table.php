<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained('gift_card_vouchers')->cascadeOnDelete();
            $table->string('shopify_order_id')->nullable()->index();
            $table->string('shopify_customer_id')->nullable()->index();
            $table->decimal('amount_used', 20, 2);
            $table->decimal('balance_before', 20, 2);
            $table->decimal('balance_after', 20, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
    }
};
