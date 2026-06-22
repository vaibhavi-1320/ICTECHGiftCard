<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->nullable()->index();
            $table->string('name');
            $table->string('tag', 100)->default('');
            $table->string('media_url')->nullable();
            $table->boolean('active')->default(true);
            $table->longText('body_html')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_templates');
    }
};
