<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('has_tail_lift')->default(false)->after('capacity_volume');
        });

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->boolean('needs_tail_lift')->default(false)->after('is_hazardous');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('has_tail_lift');
        });

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn('needs_tail_lift');
        });
    }
};
