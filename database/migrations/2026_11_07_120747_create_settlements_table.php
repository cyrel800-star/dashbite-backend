<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_record_id')->constrained()->cascadeOnDelete();

            // Types:
            //   rider_remittance       = rider → admin (COD cash)
            //   restaurant_payout      = admin → restaurant
            $table->enum('type', ['rider_remittance', 'restaurant_payout']);

            // Who is on each side
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete(); // rider (for remittance) or admin
            $table->foreignId('to_restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete(); // for payouts

            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'flagged'])->default('pending');

            $table->foreignId('recorded_by')->constrained('users'); // admin who recorded
            $table->string('notes')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};