<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->enum('outcome', ['sent', 'throttled']);
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->index(['email', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};