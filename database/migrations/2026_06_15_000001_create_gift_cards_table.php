<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('shopify_product_id')->nullable()->index();
            $table->string('shopify_product_variant_id')->nullable()->index();
            $table->string('name');
            $table->decimal('amount', 20, 2);
            $table->string('code_prefix', 20)->default('');
            $table->unsignedInteger('validity_days')->default(365);
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->string('image_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
