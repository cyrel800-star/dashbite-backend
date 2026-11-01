<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('phone')->nullable();
            $table->json('service_boundary')->nullable();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();

            $table->index('is_open');
            $table->index('is_disabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};