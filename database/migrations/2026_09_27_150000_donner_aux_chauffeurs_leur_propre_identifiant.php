<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * La fiche chauffeur n'emprunte plus l'identifiant du compte : elle a
     * sa propre numerotation et renvoie au compte par user_id. Un chauffeur
     * peut ainsi exister sans compte (interimaire, sous-traitant) et un
     * compte supprime ne fait plus disparaitre l'historique des missions.
     *
     * Les fiches existantes gardent leur identifiant, egal a celui de leur
     * compte ; les nouvelles sont numerotees apres le plus grand des deux.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['id']);
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
        });

        DB::statement('UPDATE drivers SET user_id = id');

        DB::statement('CREATE SEQUENCE IF NOT EXISTS drivers_id_seq OWNED BY drivers.id');
        DB::statement("SELECT setval('drivers_id_seq', GREATEST(
            (SELECT COALESCE(MAX(id), 0) FROM drivers),
            (SELECT COALESCE(MAX(id), 0) FROM users)
        ) + 1000)");
        DB::statement("ALTER TABLE drivers ALTER COLUMN id SET DEFAULT nextval('drivers_id_seq')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drivers ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS drivers_id_seq');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->foreign('id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
