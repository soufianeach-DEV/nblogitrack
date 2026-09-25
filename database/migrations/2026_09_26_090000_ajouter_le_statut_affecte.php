<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * L'affectation faisait passer l'ordre directement « en cours ». Le
 * chauffeur ne pouvait donc jamais confirmer la prise en charge, et le
 * client ne distinguait pas un camion prevu d'un camion parti.
 *
 * ASSIGNED s'intercale : le planificateur affecte, le chauffeur enleve.
 * picked_up_at date l'enlevement, meme sans position GPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transport_orders DROP CONSTRAINT transport_orders_status_check');
        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_status_check
            CHECK (status IN ('PENDING', 'ASSIGNED', 'IN_PROGRESS', 'DELIVERED', 'CANCELLED'))");

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        DB::table('transport_orders')->where('status', 'ASSIGNED')->update(['status' => 'IN_PROGRESS']);

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn('picked_up_at');
        });

        DB::statement('ALTER TABLE transport_orders DROP CONSTRAINT transport_orders_status_check');
        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_status_check
            CHECK (status IN ('PENDING', 'IN_PROGRESS', 'DELIVERED', 'CANCELLED'))");
    }
};
