<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); // one rating per order

            // Separate ratings for restaurant and rider (W7.01 AC #1)
            $table->tinyInteger('restaurant_rating')->unsigned()->nullable();
            $table->tinyInteger('rider_rating')->unsigned()->nullable();
            $table->text('restaurant_comment')->nullable();
            $table->text('rider_comment')->nullable();

            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};