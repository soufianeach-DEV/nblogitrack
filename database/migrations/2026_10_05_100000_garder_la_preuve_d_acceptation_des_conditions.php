<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Preuve de l'acceptation des conditions generales a l'inscription : la
 * date et la version (date de mise a jour de la page) du texte accepte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('conditions_acceptees_le')->nullable();
            $table->string('conditions_version', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['conditions_acceptees_le', 'conditions_version']);
        });
    }
};
