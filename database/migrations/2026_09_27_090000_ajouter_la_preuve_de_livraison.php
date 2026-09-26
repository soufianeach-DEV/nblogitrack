<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * La livraison garde son heure exacte, le nom de la personne qui a
     * receptionne la marchandise et les reserves eventuelles : c'est la
     * base d'une preuve de livraison (et, plus tard, d'une e-CMR signee).
     */
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('picked_up_at');
            $table->string('received_by', 120)->nullable()->after('delivered_at');
            $table->text('delivery_reserves')->nullable()->after('received_by');
        });

        DB::table('transport_orders')
            ->where('status', 'DELIVERED')
            ->whereNull('delivered_at')
            ->whereNotNull('actual_delivery_date')
            ->update(['delivered_at' => DB::raw('actual_delivery_date')]);
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'received_by', 'delivery_reserves']);
        });
    }
};
