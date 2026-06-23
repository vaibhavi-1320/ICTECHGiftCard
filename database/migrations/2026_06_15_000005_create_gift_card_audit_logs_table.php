<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_card_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained('gift_card_vouchers')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->longText('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_audit_logs');
    }
};
