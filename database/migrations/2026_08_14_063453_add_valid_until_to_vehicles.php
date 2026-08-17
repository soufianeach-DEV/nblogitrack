<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('inspection_valid_until')->nullable()->after('inspection_date');
        });

        DB::statement("UPDATE vehicles
            SET inspection_valid_until = inspection_date + INTERVAL '1 year'
            WHERE inspection_date IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('inspection_valid_until');
        });
    }
};
