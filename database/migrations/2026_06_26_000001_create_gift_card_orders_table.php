<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('shopify_order_id')->index();
            $table->string('shopify_order_number')->nullable()->index();
            $table->string('shopify_customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('shopify_product_id')->nullable()->index();
            $table->string('shopify_variant_id')->nullable()->index();
            $table->string('gift_card_product_name');
            $table->decimal('amount', 20, 2);
            $table->string('template_name')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->string('sender_name')->default('');
            $table->longText('personal_message')->nullable();
            $table->date('delivery_date');
            $table->string('status', 40)->default('pending')->index();
            $table->timestamps();
        });

        Schema::table('gift_card_vouchers', function (Blueprint $table): void {
            $table->foreignId('gift_card_order_id')->nullable()->after('gift_card_id')->constrained('gift_card_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_vouchers', function (Blueprint $table): void {
            $table->dropForeign(['gift_card_order_id']);
            $table->dropColumn('gift_card_order_id');
        });

        Schema::dropIfExists('gift_card_orders');
    }
};
