<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Une traduction retouchee depuis l'ecran Traductions n'est plus
     * ecrasee par la synchronisation qui suit chaque mise a jour.
     */
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->boolean('modifiee_a_la_main')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('modifiee_a_la_main');
        });
    }
};
