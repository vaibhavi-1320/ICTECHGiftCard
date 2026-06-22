<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->foreign('template_id')->references('id')->on('gift_card_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->dropForeign(['template_id']);
        });
    }
};
