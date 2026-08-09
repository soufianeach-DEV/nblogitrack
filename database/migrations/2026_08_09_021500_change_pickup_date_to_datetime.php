<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dateTime('pickup_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->date('pickup_date')->nullable()->change();
        });
    }
};
