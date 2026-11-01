<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['customer', 'restaurant', 'rider', 'admin'])
                  ->default('customer')->after('phone');
            $table->enum('account_status', ['pending', 'approved', 'disabled'])
                  ->default('pending')->after('role');
            $table->index('role');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['account_status']);
            $table->dropColumn(['phone', 'role', 'account_status']);
        });
    }
};