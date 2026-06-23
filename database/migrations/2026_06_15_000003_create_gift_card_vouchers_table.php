<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_order_id')->nullable()->index();
            $table->string('shopify_order_line_item_id')->nullable()->index();
            $table->string('shopify_customer_id')->nullable()->index();
            $table->string('code', 64)->unique();
            $table->decimal('original_amount', 20, 2);
            $table->decimal('remaining_balance', 20, 2);
            $table->string('currency', 10);
            $table->string('sender_name')->default('');
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->longText('personal_message')->nullable();
            $table->date('scheduled_send_date');
            $table->timestamp('sent_at')->nullable();
            $table->date('expires_at');
            $table->string('status', 40)->index();
            $table->string('used_in_order_number', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['gift_card_id', 'status']);
            $table->index(['scheduled_send_date', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_vouchers');
    }
};
