<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'closed', 'expired'])->default('pending');
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_offers');
    }
};