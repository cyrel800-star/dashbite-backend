<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->enum('method', ['cod', 'online'])->default('cod');
            $table->enum('status', ['pending', 'collected', 'settled', 'failed'])->default('pending');

            $table->decimal('amount', 10, 2);              // total paid by customer
            $table->decimal('rider_cut', 10, 2)->default(0.00);
            $table->decimal('platform_fee', 10, 2)->default(0.00);
            $table->decimal('restaurant_share', 10, 2)->default(0.00);

            $table->timestamp('collected_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};