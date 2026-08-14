<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Le chauffeur de transport routier est un ouvrier de la
            // commission paritaire 140.03. L'independant existe, mais le
            // faux independant est l'un des points les plus controles du
            // secteur : le statut se declare, il ne se devine pas.
            $table->string('employment_status', 20)->default('OUVRIER')->after('id');
            $table->date('hired_on')->nullable()->after('employment_status');

            // Anticiper les departs. La date de naissance donne l'age legal ;
            // la retraite anticipee depend de la carriere complete, tous
            // employeurs confondus, que cette application ne connait pas.
            $table->date('birth_date')->nullable()->after('hired_on');
            $table->date('retirement_planned_on')->nullable()->after('birth_date');

            // Deux titres obligatoires pour conduire en professionnel, au
            // meme titre que le permis : la qualification code 95 et la
            // carte tachygraphe, valables cinq ans chacune.
            $table->date('cpc_expiry')->nullable()->after('license_expiry');
            $table->date('tacho_card_expiry')->nullable()->after('cpc_expiry');

            // Une sortie ne supprime jamais la ligne : l'historique des
            // missions doit rester lisible apres le depart.
            $table->date('left_on')->nullable();
            $table->string('departure_reason', 20)->nullable();

            $table->index('left_on');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['left_on']);
            $table->dropColumn([
                'employment_status', 'hired_on', 'birth_date', 'retirement_planned_on',
                'cpc_expiry', 'tacho_card_expiry', 'left_on', 'departure_reason',
            ]);
        });
    }
};
