<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shopify_shop_id')->nullable()->index();
            $table->string('shopify_domain')->unique();
            $table->text('access_token')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
