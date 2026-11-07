<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Source of order: web, phone, message, walk_in
            $table->enum('source', ['web', 'phone', 'message', 'walk_in'])
                  ->default('web')
                  ->after('payment_method');

            // For external orders — customer details as given by caller
            // (also useful for walk-ins even if linked to a registered user)
            $table->string('external_customer_name')->nullable()->after('source');
            $table->string('external_customer_phone')->nullable()->after('external_customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['source', 'external_customer_name', 'external_customer_phone']);
        });
    }
};