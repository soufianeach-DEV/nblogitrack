<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropUnique(['invoice_id', 'transport_order_id']);
            $table->unique('transport_order_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropUnique(['transport_order_id']);
            $table->unique(['invoice_id', 'transport_order_id']);
        });
    }
};
