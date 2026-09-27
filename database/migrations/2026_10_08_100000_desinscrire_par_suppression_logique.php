<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Desinscription par suppression logique (soft delete). Une entreprise qui a
 * deja transporte ou ete facturee ne peut pas etre effacee : la loi impose de
 * garder ses pieces. Elle et ses comptes recoivent une date de suppression,
 * les donnees personnelles des comptes sont anonymisees, les commandes et
 * les factures restent intactes.
 *
 * Le numero de TVA reste unique parmi les entreprises actives seulement :
 * une entreprise desinscrite peut se reinscrire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique('clients_vat_number_unique');
        });

        DB::statement('CREATE UNIQUE INDEX clients_vat_number_unique ON clients (vat_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clients_vat_number_unique');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('vat_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
