<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('first_online_at')->nullable();
            $table->timestamp('last_offline_at')->nullable();
            $table->unsignedInteger('deliveries_count')->default(0);
            $table->timestamps();

            $table->unique(['rider_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_attendances');
    }
};