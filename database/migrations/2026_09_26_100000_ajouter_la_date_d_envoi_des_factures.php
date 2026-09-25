<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Une facture emise part desormais par courriel. sent_at retient le
 * dernier envoi reussi : vide, il signale une facture que le client n'a
 * pas recue et qu'il faut renvoyer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('paid_on');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
