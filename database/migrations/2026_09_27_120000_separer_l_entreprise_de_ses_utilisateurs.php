<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Une entreprise cliente n'est plus un utilisateur : elle a sa propre
     * numerotation et plusieurs comptes peuvent y travailler, chacun avec
     * son role (administrateur, commandes, comptabilite). Supprimer un
     * compte ne supprime plus l'entreprise, ses expeditions ni ses
     * factures.
     *
     * Les entreprises existantes gardent leur identifiant ; le compte qui
     * les a inscrites en devient l'administrateur.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['id']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS clients_id_seq OWNED BY clients.id');
        DB::statement("SELECT setval('clients_id_seq', GREATEST((SELECT COALESCE(MAX(id), 0) FROM clients), 1))");
        DB::statement("ALTER TABLE clients ALTER COLUMN id SET DEFAULT nextval('clients_id_seq')");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('role')->constrained('clients')->restrictOnDelete();
            $table->string('company_role', 12)->nullable()->after('client_id');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_company_role_check
            CHECK (company_role IS NULL OR company_role IN ('ADMIN', 'ORDERS', 'BILLING'))");

        DB::statement("UPDATE users SET client_id = users.id, company_role = 'ADMIN'
            WHERE role = 'CLIENT' AND EXISTS (SELECT 1 FROM clients WHERE clients.id = users.id)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('company_role');
        });

        DB::statement('ALTER TABLE clients ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS clients_id_seq');

        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
