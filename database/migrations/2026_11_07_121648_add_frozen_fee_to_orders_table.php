<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // W6.05 AC #2: Snapshot of fee and boundary at order time
            $table->decimal('frozen_delivery_fee', 10, 2)->nullable()->after('delivery_fee');
            $table->json('frozen_service_boundary')->nullable()->after('frozen_delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['frozen_delivery_fee', 'frozen_service_boundary']);
        });
    }
};