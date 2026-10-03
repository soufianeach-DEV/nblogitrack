<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Les especes ne sont plus un moyen de paiement : la clientele paie par
 * Stripe ou par virement, et rien ne permet de remettre des especes. Les
 * paiements deja notes en especes passent en « Autre », comme leur trace
 * au journal, et les libelles devenus inutiles sont retires.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')->where('method', 'CASH')->update(['method' => 'OTHER']);

        DB::table('activity_logs')
            ->where('action', 'invoice.paid')
            ->whereRaw("properties->>'methode' = 'CASH'")
            ->update(['properties' => DB::raw("jsonb_set(properties::jsonb, '{methode}', '\"OTHER\"')::json")]);

        DB::table('translations')->whereIn('cle', ['facture.methode_especes', 'journal.methode_especes'])->delete();
    }

    public function down(): void
    {
        // Les paiements passes en « Autre » ne se distinguent plus des
        // autres : il n'y a rien a restaurer.
    }
};
