<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Availability
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(true); // false = already delivering / busy

            // Location
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();

            $table->timestamps();

            $table->index(['is_online', 'is_available']);
            $table->index('last_location_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');
    }
};