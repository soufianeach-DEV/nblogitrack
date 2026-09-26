<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un conge, une formation, un passage au garage se prevoient : le seul
 * drapeau « disponible » valait pour l'instant present, et un chauffeur en
 * conge la semaine prochaine restait affectable pour cette semaine-la.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indisponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->cascadeOnDelete();
            $table->string('vehicle_registration')->nullable();
            $table->foreign('vehicle_registration')->references('registration')->on('vehicles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('du');
            $table->date('au');
            $table->string('motif', 20);
            $table->string('commentaire', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'du', 'au']);
            $table->index(['vehicle_registration', 'du', 'au']);
        });

        DB::statement('ALTER TABLE indisponibilites ADD CONSTRAINT indisponibilites_un_seul_objet CHECK ((driver_id IS NULL) <> (vehicle_registration IS NULL))');
        DB::statement('ALTER TABLE indisponibilites ADD CONSTRAINT indisponibilites_periode CHECK (au >= du)');
    }

    public function down(): void
    {
        Schema::dropIfExists('indisponibilites');
    }
};
