<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // For COD orders — amount collected by rider
            $table->decimal('cash_collected', 10, 2)->nullable()->after('delivered_at');
            $table->timestamp('cash_collected_at')->nullable()->after('cash_collected');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cash_collected', 'cash_collected_at']);
        });
    }
};