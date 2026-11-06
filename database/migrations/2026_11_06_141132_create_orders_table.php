<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('idempotency_key')->unique(); // W3.04 AC #3: duplicate prevention

            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'pending',      // created, waiting restaurant decision
                'accepted',     // restaurant accepted, finding rider
                'rejected',     // restaurant rejected
                'preparing',    // restaurant preparing
                'ready',        // ready for pickup
                'picked_up',    // rider picked up
                'delivered',    // completed
                'cancelled',    // cancelled by customer or system
                'no_rider',     // no rider found at boundary
            ])->default('pending');

            $table->enum('payment_method', ['cod', 'online'])->default('cod');

            // W3.04 AC #1: Totals tracking
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(30.00);
            $table->decimal('total', 10, 2);

            // W3.04 AC #2: Service boundary check
            $table->string('delivery_address');
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('placed_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};